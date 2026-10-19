<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    edgeJson(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

[$input, $rawBody] = edgeReadJsonBody();
edgeVerifyRequestSignature($rawBody);
edgeEnsureClickIngestTable($pdo);

$clickId = trim((string)($input['click_id'] ?? ''));
$linkId = (int)($input['link_id'] ?? 0);
$slug = edgeNormalizeSlug((string)($input['slug'] ?? ''));
$subdomain = edgeNormalizeSubdomain((string)($input['subdomain'] ?? 'ROOT'));
$variantId = isset($input['variant_id']) ? (int)$input['variant_id'] : null;
$source = edgeSourceFromQuery((string)($input['source'] ?? 'digital'));

if ($clickId === '' || !preg_match('/^[a-f0-9-]{16,64}$/i', $clickId)) {
    edgeJson(400, ['ok' => false, 'error' => 'invalid_click_id']);
}

$resolvedLink = null;
if ($linkId > 0) {
    $stmt = $pdo->prepare("SELECT id, user_id, path, subdomain FROM links WHERE id = ? LIMIT 1");
    $stmt->execute([$linkId]);
    $resolvedLink = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($slug !== '') {
    $stmt = $pdo->prepare("SELECT id, user_id, path, subdomain FROM links WHERE subdomain = ? AND path = ? LIMIT 1");
    $stmt->execute([$subdomain, $slug]);
    $resolvedLink = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$resolvedLink) {
    edgeJson(404, ['ok' => false, 'error' => 'link_not_found']);
}

$resolvedLinkId = (int)$resolvedLink['id'];
$linkUserId = (int)$resolvedLink['user_id'];
$country = trim((string)($input['country'] ?? ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'Desconhecido')));
$region = trim((string)($input['region'] ?? ($_SERVER['HTTP_CF_REGION'] ?? 'Desconhecido')));
$city = trim((string)($input['city'] ?? ($_SERVER['HTTP_CF_IPCITY'] ?? 'Desconhecido')));
$userAgent = mb_substr((string)($input['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido')), 0, 2000);
$referer = edgeSafeReferer((string)($input['referer'] ?? ($_SERVER['HTTP_REFERER'] ?? 'Acesso Direto')));
$visitorIp = edgeSafeVisitorIp((string)($input['ip'] ?? ''), getRealIP());
$incomingQueryRaw = trim((string)($input['incoming_query'] ?? ''));
if (strlen($incomingQueryRaw) > 2048) {
    $incomingQueryRaw = mb_substr($incomingQueryRaw, 0, 2048);
}
$incomingQueryPayload = $incomingQueryRaw !== '' ? $incomingQueryRaw : null;
$clicksLogHasIncoming = pdoColumnExists($pdo, 'clicks_log', 'incoming_query');

try {
    $pdo->beginTransaction();

    $dedupeStmt = $pdo->prepare("INSERT INTO edge_click_ingest (click_id, link_id, received_at) VALUES (?, ?, NOW())");
    $dedupeStmt->execute([$clickId, $resolvedLinkId]);

    $updateStmt = $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?");
    $updateStmt->execute([$resolvedLinkId]);

    $analyticsPolicy = edgeGetAnalyticsPolicyForUser($pdo, $linkUserId);
    $logged = false;

    if ($analyticsPolicy['enabled']) {
        if ($clicksLogHasIncoming && $incomingQueryPayload !== null) {
            $logStmt = $pdo->prepare(
                "INSERT INTO clicks_log (click_uid, link_id, visitor_ip, visitor_country, visitor_region, visitor_city, user_agent, referer, incoming_query, source, variant_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $logStmt->execute([
                $clickId,
                $resolvedLinkId,
                $visitorIp,
                $country !== '' ? mb_substr($country, 0, 50) : 'Desconhecido',
                $region !== '' ? mb_substr($region, 0, 100) : 'Desconhecido',
                $city !== '' ? mb_substr($city, 0, 100) : 'Desconhecido',
                $userAgent,
                $referer,
                $incomingQueryPayload,
                $source,
                $variantId,
            ]);
        } else {
            $logStmt = $pdo->prepare(
                "INSERT INTO clicks_log (click_uid, link_id, visitor_ip, visitor_country, visitor_region, visitor_city, user_agent, referer, source, variant_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $logStmt->execute([
                $clickId,
                $resolvedLinkId,
                $visitorIp,
                $country !== '' ? mb_substr($country, 0, 50) : 'Desconhecido',
                $region !== '' ? mb_substr($region, 0, 100) : 'Desconhecido',
                $city !== '' ? mb_substr($city, 0, 100) : 'Desconhecido',
                $userAgent,
                $referer,
                $source,
                $variantId,
            ]);
        }
        $logged = true;
    }

    $pdo->commit();
    edgeJson(202, [
        'ok' => true,
        'accepted' => true,
        'deduped' => false,
        'logged' => $logged,
        'link_id' => $resolvedLinkId,
        'click_id' => $clickId,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((int)$e->getCode() === 23000) {
        edgeJson(200, [
            'ok' => true,
            'accepted' => true,
            'deduped' => true,
            'logged' => false,
            'link_id' => $resolvedLinkId,
            'click_id' => $clickId,
        ]);
    }

    error_log('edge/click error: ' . $e->getMessage());
    edgeJson(500, ['ok' => false, 'error' => 'internal_error']);
}

function edgeEnsureClickIngestTable(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS edge_click_ingest (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            click_id VARCHAR(64) NOT NULL,
            link_id INT(11) NOT NULL,
            received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_edge_click_id (click_id),
            KEY idx_edge_click_link (link_id),
            CONSTRAINT edge_click_ingest_link_fk FOREIGN KEY (link_id) REFERENCES links (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $initialized = true;
}

function edgeSafeVisitorIp(string $payloadIp, string $fallbackIp): string
{
    $candidate = trim($payloadIp);
    if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
        return $candidate;
    }
    return $fallbackIp;
}

function edgeGetAnalyticsPolicyForUser(PDO $pdo, int $userId): array
{
    try {
        $adminStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ? LIMIT 1");
        $adminStmt->execute([$userId]);
        if ((int)$adminStmt->fetchColumn() === 1) {
            return ['enabled' => true, 'retention_days' => 0];
        }
    } catch (PDOException $e) {
        // Ignore legacy schema without is_admin.
    }

    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(op.id, p.id) AS effective_plan_id,
            COALESCE(op.analytics_days, p.analytics_days, 0) AS analytics_days,
            COALESCE(op.features_json, p.features_json, '{}') AS features_json
        FROM users u
        LEFT JOIN user_account_overrides o ON o.user_id = u.id AND o.custom_plan_id IS NOT NULL
        LEFT JOIN plans p ON p.id = u.current_plan_id
        LEFT JOIN plans op ON op.id = o.custom_plan_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['enabled' => false, 'retention_days' => 0];
    }

    $features = json_decode((string)($row['features_json'] ?? '{}'), true);
    if (!is_array($features)) {
        $features = [];
    }

    $analyticsDays = (int)($row['analytics_days'] ?? 0);
    if ($analyticsDays <= 0) {
        $analyticsDays = (int)($features['analytics_days'] ?? $features['analytics_retention_days'] ?? 0);
    }

    $isAnalyticsEnabled = !empty($features['analytics']) && $analyticsDays > 0;
    return [
        'enabled' => $isAnalyticsEnabled,
        'retention_days' => max(0, $analyticsDays),
    ];
}

