<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json');
require_once 'db.php';
require_once 'link_pass_through_query.php';
require_once 'auth_guard.php';
$pdo->exec("SET time_zone = '-03:00';");

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function normalizeCampaignFilter(string $name): string {
    $value = trim(mb_strtolower($name, 'UTF-8'));
    $value = preg_replace('/\s+/', ' ', $value);
    return (string)$value;
}

function bindCampaignFilterParams(PDOStatement $stmt, string $campaignFilter): void {
    $raw = trim($campaignFilter);
    $encoded = urlencode($raw);
    $rawEncoded = rawurlencode($raw);
    $plusEncoded = str_replace('%20', '+', $rawEncoded);
    $stmt->bindValue(':campaignMatchRaw', '%utm_campaign=' . $raw . '%');
    $stmt->bindValue(':campaignMatchUrlEncoded', '%utm_campaign=' . $encoded . '%');
    $stmt->bindValue(':campaignMatchRawEncoded', '%utm_campaign=' . $rawEncoded . '%');
    $stmt->bindValue(':campaignMatchPlusEncoded', '%utm_campaign=' . $plusEncoded . '%');
    $stmt->bindValue(':campaignNormalized', normalizeCampaignFilter($raw));
}

function parsePositiveIntFilter($raw): ?int {
    if ($raw === null) {
        return null;
    }
    $value = trim((string)$raw);
    if ($value === '' || $value === 'all') {
        return null;
    }
    $parsed = (int)$value;
    return $parsed > 0 ? $parsed : null;
}

function normalizeSubdomainFilter($raw): ?string {
    if ($raw === null) {
        return null;
    }
    $value = trim((string)$raw);
    if ($value === '' || $value === 'all') {
        return null;
    }
    if ($value === 'none') {
        return 'ROOT';
    }
    $normalized = preg_replace('/[^a-z0-9-]/', '', strtolower($value));
    return $normalized !== '' ? $normalized : null;
}

function bindAdvancedAnalyticsFilterParams(PDOStatement $stmt, ?int $folderIdFilter, ?int $tagIdFilter, ?string $subdomainFilter): void {
    if ($folderIdFilter !== null) {
        $stmt->bindValue(':folderIdFilter', $folderIdFilter, PDO::PARAM_INT);
    }
    if ($tagIdFilter !== null) {
        $stmt->bindValue(':tagIdFilter', $tagIdFilter, PDO::PARAM_INT);
    }
    if ($subdomainFilter !== null) {
        $stmt->bindValue(':subdomainFilter', $subdomainFilter);
    }
}

function percentileFromSorted(array $sorted, float $percent): float {
    $n = count($sorted);
    if ($n === 0) return 0.0;
    if ($n === 1) return (float)$sorted[0];
    $index = ($n - 1) * $percent;
    $lo = (int)floor($index);
    $hi = (int)ceil($index);
    if ($lo === $hi) return (float)$sorted[$lo];
    $weight = $index - $lo;
    return ((float)$sorted[$lo] * (1 - $weight)) + ((float)$sorted[$hi] * $weight);
}

function computeDwellInsightsFromRows(array $rows): array {
    $validRaw = [];
    $linksWithAnySessions = [];
    foreach ($rows as $row) {
        $duration = (int)($row['duration_ms'] ?? 0);
        $linkId = (int)($row['link_id'] ?? 0);
        if ($linkId > 0) {
            $linksWithAnySessions[$linkId] = true;
        }
        if ($duration >= 1000) {
            $validRaw[] = $duration;
        }
    }

    if (count($validRaw) === 0) {
        return [
            'avg_seconds' => 0.0,
            'median_seconds' => 0.0,
            'valid_sessions' => 0,
            'discarded_sessions' => count($rows),
            'links_with_valid' => 0,
            'links_with_any_sessions' => count($linksWithAnySessions),
            'series' => [],
            'valid_points' => []
        ];
    }

    $filtered = [];
    $dailyBuckets = [];
    $linksWithValid = [];
    $validPoints = [];
    foreach ($rows as $row) {
        $duration = (int)($row['duration_ms'] ?? 0);
        if ($duration < 1000) continue;
        $filtered[] = $duration;
        $linkId = (int)($row['link_id'] ?? 0);
        if ($linkId > 0) {
            $linksWithValid[$linkId] = true;
        }
        $day = (string)($row['day'] ?? '');
        if ($day === '') continue;
        if (!isset($dailyBuckets[$day])) {
            $dailyBuckets[$day] = ['sum' => 0, 'count' => 0];
        }
        $dailyBuckets[$day]['sum'] += $duration;
        $dailyBuckets[$day]['count']++;
        $validPoints[] = [
            'started_at' => (string)($row['started_at'] ?? ''),
            'seconds' => round($duration / 1000, 3)
        ];
    }

    if (count($filtered) === 0) {
        return [
            'avg_seconds' => 0.0,
            'median_seconds' => 0.0,
            'valid_sessions' => 0,
            'discarded_sessions' => count($rows),
            'links_with_valid' => 0,
            'links_with_any_sessions' => count($linksWithAnySessions),
            'series' => [],
            'valid_points' => []
        ];
    }

    sort($filtered);
    $median = percentileFromSorted($filtered, 0.5);
    $avg = array_sum($filtered) / count($filtered);

    ksort($dailyBuckets);
    $series = [];
    foreach ($dailyBuckets as $day => $bucket) {
        if ((int)$bucket['count'] <= 0) continue;
        $series[] = [
            'day' => $day,
            'avg_seconds' => round(($bucket['sum'] / $bucket['count']) / 1000, 2),
            'samples' => (int)$bucket['count']
        ];
    }

    return [
        'avg_seconds' => round($avg / 1000, 2),
        'median_seconds' => round($median / 1000, 2),
        'valid_sessions' => count($filtered),
        'discarded_sessions' => max(0, count($rows) - count($filtered)),
        'links_with_valid' => count($linksWithValid),
        'links_with_any_sessions' => count($linksWithAnySessions),
        'series' => $series,
        'valid_points' => $validPoints
    ];
}

$userId = requireAuthenticatedUserNotBanned($pdo, false, false);
$teamUserIds = $_SESSION['team_users'] ?? [$userId];
if (!is_array($teamUserIds)) {
    $teamUserIds = [$userId];
}
$teamUserIds = array_values(array_unique(array_map('intval', $teamUserIds)));
if (empty($teamUserIds)) {
    $teamUserIds = [$userId];
}
$teamUserIdsStr = implode(',', array_map('intval', $teamUserIds));
$action = $_GET['action'] ?? '';

$hasPlans = tableExists($pdo, 'plans');
$hasOverrides = tableExists($pdo, 'user_account_overrides');
$hasCurrentPlanId = columnExists($pdo, 'users', 'current_plan_id');
$hasCampaignsTable = tableExists($pdo, 'campaigns');
$hasLinksCampaignId = columnExists($pdo, 'links', 'campaign_id');
$hasFolderColumn = columnExists($pdo, 'links', 'folder_id');
$hasFoldersTable = tableExists($pdo, 'link_folders');
$hasTagsTable = tableExists($pdo, 'link_tags');
$hasTagMapTable = tableExists($pdo, 'link_tag_map');
$hasIncomingQueryColumn = columnExists($pdo, 'clicks_log', 'incoming_query');
$campaignJoin = ($hasCampaignsTable && $hasLinksCampaignId)
    ? " LEFT JOIN campaigns c ON c.id = l.campaign_id AND c.user_id = l.user_id "
    : '';
$billingPolicyEnabled = $hasPlans && $hasOverrides && $hasCurrentPlanId;

$policyJoin = '';
$analyticsRetentionFilter = '';
if ($billingPolicyEnabled) {
    $policyJoin = "
        JOIN users u ON u.id = l.user_id
        LEFT JOIN user_account_overrides o ON o.user_id = u.id AND o.custom_plan_id IS NOT NULL
        LEFT JOIN plans p ON p.id = u.current_plan_id
        LEFT JOIN plans op ON op.id = o.custom_plan_id
    ";
    $analyticsRetentionDaysExpr = "COALESCE(
        NULLIF(op.analytics_days, 0),
        NULLIF(p.analytics_days, 0),
        CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(op.features_json, p.features_json, '{}'), '$.analytics_days')) AS UNSIGNED),
        CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(op.features_json, p.features_json, '{}'), '$.analytics_retention_days')) AS UNSIGNED),
        0
    )";

    // Regra resiliente: quando houver retenção definida, aplica janela; caso contrário, não bloqueia dados.
    $analyticsRetentionFilter = "
        AND (
            $analyticsRetentionDaysExpr <= 0
            OR cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $analyticsRetentionDaysExpr DAY)
        )
    ";
}

// Bypass Global Admin: acesso ilimitado a analytics, sem janela de retenção.
// Mantém $policyJoin (algumas queries referenciam os aliases u/p/op) e apenas
// zera o filtro de retenção para que todos os cliques do time do admin apareçam.
if (isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1) {
    $analyticsRetentionFilter = '';
}

// BUSCA LISTA DE CAMPANHAS VIRTUAIS
if ($action === 'get_campaigns') {
    $counts = [];
    if ($hasCampaignsTable && $hasLinksCampaignId) {
        $stmtCamp = $pdo->prepare("
            SELECT c.name, COUNT(l.id) AS link_count
            FROM campaigns c
            LEFT JOIN links l ON l.campaign_id = c.id
            WHERE c.user_id IN ($teamUserIdsStr)
            GROUP BY c.id, c.name
            ORDER BY c.updated_at DESC
        ");
        $stmtCamp->execute();
        foreach ($stmtCamp->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name !== '') {
                $counts[$name] = (int)($row['link_count'] ?? 0);
            }
        }
    }

    $selectCols = $hasLinksCampaignId ? "original_url, campaign_id" : "original_url";
    $stmt = $pdo->prepare("SELECT $selectCols FROM links WHERE user_id IN ($teamUserIdsStr)");
    $stmt->execute();
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($urls as $row) {
        if ($hasLinksCampaignId && !empty($row['campaign_id'])) {
            continue;
        }
        $url = (string)($row['original_url'] ?? '');
        $q = parse_url((string)$url, PHP_URL_QUERY);
        if (!$q) continue;
        parse_str($q, $params);
        if (!empty($params['utm_campaign'])) {
            $name = (string)$params['utm_campaign'];
            $counts[$name] = max(($counts[$name] ?? 0), 0) + 1;
        }
    }

    $campaignData = [];
    foreach ($counts as $name => $count) {
        $campaignData[] = ['name' => $name, 'link_count' => $count];
    }
    echo json_encode(['success' => true, 'campaigns' => $campaignData]);
    exit;
}

if ($action === 'dashboard_widgets') {
    $period = (int)($_GET['period'] ?? 14);
    if (!in_array($period, [7, 14, 30], true)) $period = 14;
    try {
        $stmt = $pdo->prepare("
            SELECT
                agg_date,
                SUM(clicks_total) AS clicks_total,
                SUM(conversions_total) AS conversions_total
            FROM analytics_daily_agg
            WHERE user_id = :userId
              AND agg_date >= DATE_SUB(CURDATE(), INTERVAL $period DAY)
            GROUP BY agg_date
            ORDER BY agg_date ASC
        ");
        $stmt->execute([':userId' => $userId]);
        $series = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(utm_campaign, ''), 'Sem campanha') AS campaign,
                SUM(clicks_total) AS clicks_total,
                SUM(conversions_total) AS conversions_total
            FROM analytics_daily_agg
            WHERE user_id = :userId
              AND agg_date >= DATE_SUB(CURDATE(), INTERVAL $period DAY)
            GROUP BY COALESCE(NULLIF(utm_campaign, ''), 'Sem campanha')
            ORDER BY conversions_total DESC, clicks_total DESC
            LIMIT 8
        ");
        $stmt2->execute([':userId' => $userId]);
        $campaigns = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'period' => $period,
            'series' => $series,
            'campaigns' => $campaigns
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Agregações indisponíveis']);
    }
    exit;
}

if ($action === 'deep_insights') {
    $period = (int)($_GET['period'] ?? 14);
    if (!in_array($period, [7, 14, 30, 90], true)) $period = 14;
    $periodSql = (string)$period;
    $prevPeriodSql = (string)($period * 2);

    $linkId = isset($_GET['link_id']) && $_GET['link_id'] !== 'all' ? (int)$_GET['link_id'] : null;
    $campaignFilter = $_GET['campaign'] ?? null;
    $folderIdFilter = parsePositiveIntFilter($_GET['folder_id'] ?? null);
    $tagIdFilter = parsePositiveIntFilter($_GET['tag_id'] ?? null);
    $subdomainFilter = normalizeSubdomainFilter($_GET['subdomain'] ?? null);
    $period = (int)($_GET['period'] ?? 30);
    if (!in_array($period, [7, 14, 30, 90], true)) {
        $period = 30;
    }
    $linkFilter = '';
    $advancedFilter = '';
    if ($linkId) $linkFilter = " AND l.id = :linkId";
    elseif ($campaignFilter) {
        $linkFilter = " AND (
            l.original_url LIKE :campaignMatchRaw
            OR l.original_url LIKE :campaignMatchUrlEncoded
            OR l.original_url LIKE :campaignMatchRawEncoded
            OR l.original_url LIKE :campaignMatchPlusEncoded";
        if ($hasCampaignsTable && $hasLinksCampaignId) {
            $linkFilter .= " OR c.normalized_name = :campaignNormalized";
        }
        $linkFilter .= "
        )";
    }
    if ($hasFolderColumn && $folderIdFilter !== null) {
        $advancedFilter .= " AND l.folder_id = :folderIdFilter";
    }
    if ($hasTagMapTable && $tagIdFilter !== null) {
        $advancedFilter .= " AND EXISTS (
            SELECT 1
            FROM link_tag_map ltm_filter
            WHERE ltm_filter.link_id = l.id
              AND ltm_filter.tag_id = :tagIdFilter
        )";
    }
    if ($subdomainFilter !== null) {
        $advancedFilter .= " AND l.subdomain = :subdomainFilter";
    }

    $dwellInsights = [
        'avg_seconds' => 0.0,
        'median_seconds' => 0.0,
        'valid_sessions' => 0,
        'discarded_sessions' => 0,
        'links_with_valid' => 0,
        'links_with_any_sessions' => 0,
        'series' => []
    ];
    if (tableExists($pdo, 'dwell_sessions')) {
        $qDwell = "
            SELECT DATE(ds.started_at) AS day, ds.started_at, ds.duration_ms, ds.link_id
            FROM dwell_sessions ds
            INNER JOIN links l ON l.id = ds.link_id
            WHERE l.user_id IN ($teamUserIdsStr)
              AND ds.started_at >= DATE_SUB(NOW(), INTERVAL $period DAY)
        ";
        if ($linkId) {
            $qDwell .= " AND l.id = :dwellLinkId";
        }
        if ($campaignFilter) {
            $qDwell .= " AND (
                l.original_url LIKE :dwellCampaignMatchRaw
                OR l.original_url LIKE :dwellCampaignMatchUrlEncoded
                OR l.original_url LIKE :dwellCampaignMatchRawEncoded
                OR l.original_url LIKE :dwellCampaignMatchPlusEncoded";
            if ($hasCampaignsTable && $hasLinksCampaignId) {
                $qDwell .= " OR EXISTS (
                    SELECT 1 FROM campaigns c_dwell
                    WHERE c_dwell.id = l.campaign_id
                      AND c_dwell.user_id = l.user_id
                      AND c_dwell.normalized_name = :dwellCampaignNormalized
                )";
            }
            $qDwell .= ")";
        }
        if ($hasFolderColumn && $folderIdFilter !== null) {
            $qDwell .= " AND l.folder_id = :dwellFolderId";
        }
        if ($hasTagMapTable && $tagIdFilter !== null) {
            $qDwell .= " AND EXISTS (
                SELECT 1
                FROM link_tag_map ltm_dwell
                WHERE ltm_dwell.link_id = l.id
                  AND ltm_dwell.tag_id = :dwellTagId
            )";
        }
        if ($subdomainFilter !== null) {
            $qDwell .= " AND l.subdomain = :dwellSubdomain";
        }

        $stmtDwell = $pdo->prepare($qDwell);
        if ($linkId) {
            $stmtDwell->bindValue(':dwellLinkId', $linkId, PDO::PARAM_INT);
        }
        if ($campaignFilter) {
            $raw = trim((string)$campaignFilter);
            $encoded = urlencode($raw);
            $rawEncoded = rawurlencode($raw);
            $plusEncoded = str_replace('%20', '+', $rawEncoded);
            $stmtDwell->bindValue(':dwellCampaignMatchRaw', '%utm_campaign=' . $raw . '%');
            $stmtDwell->bindValue(':dwellCampaignMatchUrlEncoded', '%utm_campaign=' . $encoded . '%');
            $stmtDwell->bindValue(':dwellCampaignMatchRawEncoded', '%utm_campaign=' . $rawEncoded . '%');
            $stmtDwell->bindValue(':dwellCampaignMatchPlusEncoded', '%utm_campaign=' . $plusEncoded . '%');
            $stmtDwell->bindValue(':dwellCampaignNormalized', normalizeCampaignFilter($raw));
        }
        if ($hasFolderColumn && $folderIdFilter !== null) {
            $stmtDwell->bindValue(':dwellFolderId', $folderIdFilter, PDO::PARAM_INT);
        }
        if ($hasTagMapTable && $tagIdFilter !== null) {
            $stmtDwell->bindValue(':dwellTagId', $tagIdFilter, PDO::PARAM_INT);
        }
        if ($subdomainFilter !== null) {
            $stmtDwell->bindValue(':dwellSubdomain', $subdomainFilter);
        }

        $stmtDwell->execute();
        $dwellRows = $stmtDwell->fetchAll(PDO::FETCH_ASSOC);
        $dwellInsights = computeDwellInsightsFromRows($dwellRows);
    }

    try {
        $qSummary = "
            SELECT
                COUNT(cl.id) AS clicks,
                COUNT(cv.id) AS conversions
            FROM clicks_log cl
            JOIN links l ON cl.link_id = l.id
            $campaignJoin
            LEFT JOIN conversions_log cv ON cv.click_id = cl.id
            $policyJoin
            WHERE l.user_id IN ($teamUserIdsStr)
              $linkFilter
              $advancedFilter
              $analyticsRetentionFilter
              AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
        ";
        $stmt = $pdo->prepare($qSummary);
        if($linkId) $stmt->bindValue(':linkId', $linkId);
        if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
        bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
        $stmt->execute();
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['clicks' => 0, 'conversions' => 0];

        $qPrev = "
            SELECT COUNT(cl.id) AS clicks
            FROM clicks_log cl
            JOIN links l ON cl.link_id = l.id
            $campaignJoin
            $policyJoin
            WHERE l.user_id IN ($teamUserIdsStr)
              $linkFilter
              $advancedFilter
              $analyticsRetentionFilter
              AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $prevPeriodSql DAY)
              AND cl.clicked_at < DATE_SUB(NOW(), INTERVAL $periodSql DAY)
        ";
        $stmtPrev = $pdo->prepare($qPrev);
        if($linkId) $stmtPrev->bindValue(':linkId', $linkId);
        if($campaignFilter) bindCampaignFilterParams($stmtPrev, (string)$campaignFilter);
        bindAdvancedAnalyticsFilterParams($stmtPrev, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
        $stmtPrev->execute();
        $prevClicks = (int)$stmtPrev->fetchColumn();

        $qAvgConvTime = "
            SELECT AVG(TIMESTAMPDIFF(MINUTE, cl.clicked_at, cv.converted_at)) AS avg_minutes
            FROM conversions_log cv
            JOIN clicks_log cl ON cv.click_id = cl.id
            JOIN links l ON cl.link_id = l.id
            $campaignJoin
            $policyJoin
            WHERE l.user_id IN ($teamUserIdsStr)
              $linkFilter
              $advancedFilter
              $analyticsRetentionFilter
              AND cv.converted_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
        ";
        $stmtAvg = $pdo->prepare($qAvgConvTime);
        if($linkId) $stmtAvg->bindValue(':linkId', $linkId);
        if($campaignFilter) bindCampaignFilterParams($stmtAvg, (string)$campaignFilter);
        bindAdvancedAnalyticsFilterParams($stmtAvg, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
        $stmtAvg->execute();
        $avgMinutes = (float)($stmtAvg->fetchColumn() ?: 0);

        $qConvTimeline = "
            SELECT DATE(cv.converted_at) AS day, COUNT(cv.id) AS total
            FROM conversions_log cv
            JOIN clicks_log cl ON cv.click_id = cl.id
            JOIN links l ON cl.link_id = l.id
            $campaignJoin
            $policyJoin
            WHERE l.user_id IN ($teamUserIdsStr)
              $linkFilter
              $advancedFilter
              $analyticsRetentionFilter
              AND cv.converted_at >= DATE_SUB(CURDATE(), INTERVAL $periodSql DAY)
            GROUP BY DATE(cv.converted_at)
            ORDER BY DATE(cv.converted_at) ASC
        ";
        $stmtConv = $pdo->prepare($qConvTimeline);
        if($linkId) $stmtConv->bindValue(':linkId', $linkId);
        if($campaignFilter) bindCampaignFilterParams($stmtConv, (string)$campaignFilter);
        bindAdvancedAnalyticsFilterParams($stmtConv, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
        $stmtConv->execute();
        $conversionsTimeline = $stmtConv->fetchAll(PDO::FETCH_ASSOC);

        $qHourly = "
            SELECT HOUR(cl.clicked_at) AS hour, COUNT(cl.id) AS clicks
            FROM clicks_log cl
            JOIN links l ON cl.link_id = l.id
            $campaignJoin
            $policyJoin
            WHERE l.user_id IN ($teamUserIdsStr)
              $linkFilter
              $advancedFilter
              $analyticsRetentionFilter
              AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
            GROUP BY HOUR(cl.clicked_at)
            ORDER BY HOUR(cl.clicked_at) ASC
        ";
        $stmtHourly = $pdo->prepare($qHourly);
        if($linkId) $stmtHourly->bindValue(':linkId', $linkId);
        if($campaignFilter) bindCampaignFilterParams($stmtHourly, (string)$campaignFilter);
        bindAdvancedAnalyticsFilterParams($stmtHourly, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
        $stmtHourly->execute();
        $hourly = $stmtHourly->fetchAll(PDO::FETCH_ASSOC);

        $incomingCol = $hasIncomingQueryColumn
            ? "COALESCE(cl.incoming_query, '') AS incoming_query"
            : "'' AS incoming_query";
        $qCampaigns = "
            SELECT l.original_url, $incomingCol, COUNT(cl.id) AS clicks,
                   SUM((SELECT COUNT(*) FROM conversions_log cv WHERE cv.click_id = cl.id)) AS conversions
            FROM clicks_log cl
            JOIN links l ON cl.link_id = l.id
            $campaignJoin
            $policyJoin
            WHERE l.user_id IN ($teamUserIdsStr)
              $linkFilter
              $advancedFilter
              $analyticsRetentionFilter
              AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
            GROUP BY l.original_url, " . ($hasIncomingQueryColumn ? "COALESCE(cl.incoming_query, '')" : "''") . "
        ";
        $stmtCamp = $pdo->prepare($qCampaigns);
        if($linkId) $stmtCamp->bindValue(':linkId', $linkId);
        if($campaignFilter) bindCampaignFilterParams($stmtCamp, (string)$campaignFilter);
        bindAdvancedAnalyticsFilterParams($stmtCamp, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
        $stmtCamp->execute();
        $campaignRows = $stmtCamp->fetchAll(PDO::FETCH_ASSOC);

        $campaignMap = [];
        foreach ($campaignRows as $row) {
            $incoming = $hasIncomingQueryColumn ? (string)($row['incoming_query'] ?? '') : '';
            $params = linkMergedAttributionParamsForClick((string)$row['original_url'], $incoming !== '' ? $incoming : null);
            $campaign = 'Sem campanha';
            if (!empty($params['utm_campaign'])) {
                $campaign = (string)$params['utm_campaign'];
            } elseif (!empty($params['ref'])) {
                $campaign = 'ref: ' . (string)$params['ref'];
            }
            if (!isset($campaignMap[$campaign])) {
                $campaignMap[$campaign] = ['campaign' => $campaign, 'clicks' => 0, 'conversions' => 0, 'efficiency' => 0.0];
            }
            $campaignMap[$campaign]['clicks'] += (int)$row['clicks'];
            $campaignMap[$campaign]['conversions'] += (int)$row['conversions'];
        }

        $campaignEfficiency = [];
        foreach ($campaignMap as $item) {
            $item['efficiency'] = $item['clicks'] > 0 ? round(($item['conversions'] / $item['clicks']) * 100, 2) : 0.0;
            $campaignEfficiency[] = $item;
        }
        usort($campaignEfficiency, static function($a, $b) {
            return $b['efficiency'] <=> $a['efficiency'];
        });
        $campaignEfficiency = array_slice($campaignEfficiency, 0, 8);

        $clicks = (int)($summary['clicks'] ?? 0);
        $conversions = (int)($summary['conversions'] ?? 0);
        $cvr = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0.0;
        $growth = $prevClicks > 0 ? round((($clicks - $prevClicks) / $prevClicks) * 100, 2) : 0.0;

        $alerts = [];
        if ($growth < -20) $alerts[] = 'Queda superior a 20% de cliques em relação ao período anterior.';
        if ($clicks > 50 && $cvr < 1) $alerts[] = 'Muitos cliques com baixa conversão. Revise CTA e página de destino.';
        if ($avgMinutes > 720) $alerts[] = 'Tempo médio de conversão elevado; considere reduzir fricção no funil.';

        $recommendations = [];
        if (!empty($campaignEfficiency)) {
            $top = $campaignEfficiency[0];
            $recommendations[] = 'Campanha "' . $top['campaign'] . '" apresenta melhor eficiência (' . number_format((float)$top['efficiency'], 1, ',', '.') . '%).';
        }
        if ($cvr < 2) $recommendations[] = 'Teste variações de oferta/copy para elevar a taxa de conversão.';
        if ($growth > 15) $recommendations[] = 'Aproveite a tendência de alta ampliando orçamento nas fontes vencedoras.';
        if (empty($recommendations)) $recommendations[] = 'Desempenho estável no período atual.';

        echo json_encode([
            'success' => true,
            'period' => $period,
            'summary' => [
                'clicks' => $clicks,
                'conversions' => $conversions,
                'cvr' => $cvr,
                'growth' => $growth,
                'avg_conversion_minutes' => round($avgMinutes, 2),
                'previous_clicks' => $prevClicks,
                'top_campaign' => $campaignEfficiency[0] ?? ['campaign' => '-', 'efficiency' => 0.0]
            ],
            'funnel' => [
                ['label' => 'Cliques', 'value' => $clicks],
                ['label' => 'Conversões', 'value' => $conversions]
            ],
            'charts' => [
                'conversions_timeline' => $conversionsTimeline,
                'hourly_performance' => $hourly,
                'campaign_efficiency' => $campaignEfficiency
            ],
            'alerts' => $alerts,
            'recommendations' => $recommendations
        ]);
    } catch (Throwable $e) {
        error_log('[analytics.deep_insights] ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Falha ao carregar insights avançados',
            'summary' => null,
            'funnel' => [],
            'charts' => [
                'conversions_timeline' => [],
                'hourly_performance' => [],
                'campaign_efficiency' => []
            ],
            'alerts' => [],
            'recommendations' => []
        ]);
    }
    exit;
}

if ($action === 'dwell_summary') {
    $period = (int)($_GET['period'] ?? 30);
    if (!in_array($period, [7, 14, 30, 90], true)) $period = 30;
    $linkId = isset($_GET['link_id']) && $_GET['link_id'] !== 'all' ? (int)$_GET['link_id'] : null;

    if (!tableExists($pdo, 'dwell_sessions')) {
        echo json_encode([
            'success' => true,
            'dwell' => [
                'avg_seconds' => 0.0,
                'median_seconds' => 0.0,
                'valid_sessions' => 0,
                'discarded_sessions' => 0,
                'links_with_valid' => 0,
                'links_with_any_sessions' => 0,
                'series' => [],
                'valid_points' => []
            ]
        ]);
        exit;
    }

    $sql = "
        SELECT DATE(ds.started_at) AS day, ds.started_at, ds.duration_ms, ds.link_id
        FROM dwell_sessions ds
        INNER JOIN links l ON l.id = ds.link_id
        WHERE l.user_id IN ($teamUserIdsStr)
          AND ds.started_at >= DATE_SUB(NOW(), INTERVAL $period DAY)
    ";
    if ($linkId !== null) {
        $sql .= " AND l.id = :linkId";
    }
    $stmt = $pdo->prepare($sql);
    if ($linkId !== null) {
        $stmt->bindValue(':linkId', $linkId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dwell = computeDwellInsightsFromRows($rows);

    echo json_encode(['success' => true, 'dwell' => $dwell]);
    exit;
}

// Recebe o ID do link específico (se houver)
$linkId = isset($_GET['link_id']) && $_GET['link_id'] !== 'all' ? (int)$_GET['link_id'] : null;
$campaignFilter = $_GET['campaign'] ?? null;
$folderIdFilter = parsePositiveIntFilter($_GET['folder_id'] ?? null);
$tagIdFilter = parsePositiveIntFilter($_GET['tag_id'] ?? null);
$subdomainFilter = normalizeSubdomainFilter($_GET['subdomain'] ?? null);
$period = (int)($_GET['period'] ?? 14);
if (!in_array($period, [7, 14, 30, 90], true)) {
    $period = 14;
}

try {
    // Busca a lista de links do usuário para preencher o dropdown
    $linksQuery = $pdo->prepare("SELECT id, path, subdomain FROM links WHERE user_id IN ($teamUserIdsStr) ORDER BY created_at DESC");
    $linksQuery->execute();
    $userLinks = $linksQuery->fetchAll(PDO::FETCH_ASSOC);

    // Constrói a condição extra se um link específico ou campanha for selecionado
    $linkFilter = "";
    $advancedFilter = "";
    if ($linkId) {
        $linkFilter = " AND l.id = :linkId";
    } elseif ($campaignFilter) {
        $linkFilter = " AND (
            l.original_url LIKE :campaignMatchRaw
            OR l.original_url LIKE :campaignMatchUrlEncoded
            OR l.original_url LIKE :campaignMatchRawEncoded
            OR l.original_url LIKE :campaignMatchPlusEncoded";
        if ($hasCampaignsTable && $hasLinksCampaignId) {
            $linkFilter .= " OR c.normalized_name = :campaignNormalized";
        }
        $linkFilter .= "
        )";
    }
    if ($hasFolderColumn && $folderIdFilter !== null) {
        $advancedFilter .= " AND l.folder_id = :folderIdFilter";
    }
    if ($hasTagMapTable && $tagIdFilter !== null) {
        $advancedFilter .= " AND EXISTS (
            SELECT 1
            FROM link_tag_map ltm_filter
            WHERE ltm_filter.link_id = l.id
              AND ltm_filter.tag_id = :tagIdFilter
        )";
    }
    if ($subdomainFilter !== null) {
        $advancedFilter .= " AND l.subdomain = :subdomainFilter";
    }

    // 1. Engajamento por Período (Diário conforme filtro selecionado)
    $qEngagement = "SELECT DATE(cl.clicked_at) as day, COUNT(cl.id) as value
                    FROM clicks_log cl 
                    JOIN links l ON cl.link_id = l.id
                    $campaignJoin
                    $policyJoin
                    WHERE l.user_id IN ($teamUserIdsStr)" . $linkFilter . $advancedFilter . $analyticsRetentionFilter . "
                    AND cl.clicked_at >= DATE_SUB(CURDATE(), INTERVAL $period DAY)
                    GROUP BY DATE(cl.clicked_at)
                    ORDER BY DATE(cl.clicked_at) ASC";
    $stmt = $pdo->prepare($qEngagement);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
    bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
    $stmt->execute();
    $engagementRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preenche dias vazios no período para manter eixo X contínuo.
    $engagementByDay = [];
    foreach ($engagementRaw as $row) {
        $day = (string)($row['day'] ?? '');
        if ($day !== '') {
            $engagementByDay[$day] = (int)($row['value'] ?? 0);
        }
    }
    $engagement = [];
    $today = new DateTimeImmutable('today');
    $startDate = $today->sub(new DateInterval('P' . max(0, $period - 1) . 'D'));
    for ($cursor = $startDate; $cursor <= $today; $cursor = $cursor->add(new DateInterval('P1D'))) {
        $dayKey = $cursor->format('Y-m-d');
        $engagement[] = [
            'label' => $cursor->format('d/m'),
            'value' => (int)($engagementByDay[$dayKey] ?? 0)
        ];
    }

    // 2. Localização (Países / Região / Cidade / Bairro — último nível exige coluna geo_neighborhood)
    $targetCountry = isset($_GET['country']) ? trim((string)$_GET['country']) : '';
    $targetRegion = isset($_GET['region']) ? trim((string)$_GET['region']) : '';
    $targetCity = isset($_GET['city']) ? trim((string)$_GET['city']) : '';
    $targetCountry = $targetCountry !== '' ? $targetCountry : null;
    $targetRegion = $targetRegion !== '' ? $targetRegion : null;
    $targetCity = $targetCity !== '' ? $targetCity : null;

    $hasGeoNeighborhoodColumn = columnExists($pdo, 'clicks_log', 'geo_neighborhood');
    if (!$hasGeoNeighborhoodColumn) {
        $targetCity = null;
    }

    if ($targetCity !== null && $targetRegion !== null && $targetCountry !== null) {
        $qLoc = "SELECT 
            CASE
                WHEN cl.geo_neighborhood IS NOT NULL AND TRIM(cl.geo_neighborhood) <> '' THEN TRIM(cl.geo_neighborhood)
                ELSE 'Sem bairro (sem geo precisa)'
            END AS label,
            COUNT(cl.id) AS value
                 FROM clicks_log cl 
                 JOIN links l ON cl.link_id = l.id 
                 $campaignJoin
                 $policyJoin
                 WHERE l.user_id IN ($teamUserIdsStr) $linkFilter $advancedFilter
                 $analyticsRetentionFilter
                 AND cl.visitor_country = :country AND cl.visitor_region = :region 
                 AND COALESCE(NULLIF(cl.visitor_city, ''), 'Não Identificada') = :city
                 GROUP BY 1 ORDER BY 2 DESC LIMIT 10";
        $stmt = $pdo->prepare($qLoc);
        $stmt->bindValue(':country', $targetCountry);
        $stmt->bindValue(':region', $targetRegion);
        $stmt->bindValue(':city', $targetCity);
    } elseif ($targetRegion !== null && $targetCountry !== null) {
        $qLoc = "SELECT COALESCE(NULLIF(cl.visitor_city, ''), 'Não Identificada') as label, COUNT(cl.id) as value 
                 FROM clicks_log cl 
                 JOIN links l ON cl.link_id = l.id 
                 $campaignJoin
                 $policyJoin
                 WHERE l.user_id IN ($teamUserIdsStr) $linkFilter $advancedFilter
                 $analyticsRetentionFilter
                 AND cl.visitor_country = :country AND cl.visitor_region = :region 
                 GROUP BY 1 ORDER BY 2 DESC LIMIT 10";
        $stmt = $pdo->prepare($qLoc);
        $stmt->bindValue(':country', $targetCountry);
        $stmt->bindValue(':region', $targetRegion);
    } elseif ($targetCountry !== null) {
        $qLoc = "SELECT COALESCE(NULLIF(cl.visitor_region, ''), 'Não Identificado') as label, COUNT(cl.id) as value 
                 FROM clicks_log cl 
                 JOIN links l ON cl.link_id = l.id 
                 $campaignJoin
                 $policyJoin
                 WHERE l.user_id IN ($teamUserIdsStr) $linkFilter $advancedFilter
                 $analyticsRetentionFilter
                 AND cl.visitor_country = :country 
                 GROUP BY 1 ORDER BY 2 DESC LIMIT 10";
        $stmt = $pdo->prepare($qLoc);
        $stmt->bindValue(':country', $targetCountry);
    } else {
        $qLoc = "SELECT COALESCE(NULLIF(cl.visitor_country, ''), 'Desconhecido') as label, COUNT(cl.id) as value 
                 FROM clicks_log cl 
                 JOIN links l ON cl.link_id = l.id 
                 $campaignJoin
                 $policyJoin
                 WHERE l.user_id IN ($teamUserIdsStr) $linkFilter $advancedFilter
                 $analyticsRetentionFilter
                 GROUP BY 1 ORDER BY 2 DESC LIMIT 10";
        $stmt = $pdo->prepare($qLoc);
    }
    
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
    bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
    $stmt->execute();
    $locations = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $locations[] = ['label' => $row['label'], 'value' => (int)$row['value']];
    }

    // 3. Sistemas Operacionais e Dispositivos (Processado via SQL para performance)
    $qOS = "SELECT 
                CASE 
                    WHEN cl.user_agent LIKE '%Windows%' THEN 'Windows'
                    WHEN cl.user_agent LIKE '%Android%' THEN 'Android'
                    WHEN cl.user_agent LIKE '%iPhone%' OR cl.user_agent LIKE '%iPad%' OR cl.user_agent LIKE '%iPod%' THEN 'iOS'
                    ELSE 'Outros'
                END as label,
                COUNT(cl.id) as value
            FROM clicks_log cl 
            JOIN links l ON cl.link_id = l.id 
            $campaignJoin
            $policyJoin
            WHERE l.user_id IN ($teamUserIdsStr) $linkFilter $advancedFilter
            $analyticsRetentionFilter
            GROUP BY 1 ORDER BY 2 DESC";
    $stmt = $pdo->prepare($qOS);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
    bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
    $stmt->execute();
    $osData = ['Windows' => 0, 'Android' => 0, 'iOS' => 0, 'Outros' => 0];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $osData[$row['label']] = (int)$row['value'];
    }

    $qDevices = "SELECT 
                    CASE 
                        WHEN LOWER(cl.user_agent) REGEXP '(android|iphone|ipad|mobile)' THEN 'Mobile'
                        ELSE 'Desktop'
                    END as label,
                    COUNT(cl.id) as value
                 FROM clicks_log cl 
                 JOIN links l ON cl.link_id = l.id 
                 $campaignJoin
                 $policyJoin
                 WHERE l.user_id IN ($teamUserIdsStr) $linkFilter $advancedFilter
                 $analyticsRetentionFilter
                 GROUP BY 1";
    $stmt = $pdo->prepare($qDevices);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
    bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
    $stmt->execute();
    $devices = ['Mobile' => 0, 'Desktop' => 0];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $devices[$row['label']] = (int)$row['value'];
    }

    // 4. Referenciadores (Origem do Tráfego)
    $qReferrers = "SELECT COALESCE(NULLIF(cl.referer, ''), 'Direto / Orgânico') as label, COUNT(cl.id) as value 
                   FROM clicks_log cl 
                   JOIN links l ON cl.link_id = l.id
                   $campaignJoin
                   $policyJoin
                   WHERE l.user_id IN ($teamUserIdsStr) " . $linkFilter . $advancedFilter . $analyticsRetentionFilter . " GROUP BY 1 ORDER BY 2 DESC LIMIT 10";
    $stmt = $pdo->prepare($qReferrers);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
    bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
    $stmt->execute();
    $referrers = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $referrers[] = ['label' => $row['label'], 'value' => (int)$row['value']];
    }

    // 5. Tipo de Origem (Digital vs QR)
    $qSources = "SELECT cl.source as label, COUNT(cl.id) as value 
                 FROM clicks_log cl 
                 JOIN links l ON cl.link_id = l.id
                 $campaignJoin
                 $policyJoin
                 WHERE l.user_id IN ($teamUserIdsStr) " . $linkFilter . $advancedFilter . $analyticsRetentionFilter . " GROUP BY 1 ORDER BY 2 DESC";
    $stmt = $pdo->prepare($qSources);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
    bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
    $stmt->execute();
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Países (Localização Resumida - Unificada com a de cima para performance)
    $countries = array_slice($locations, 0, 5);

    // --- UTM ANALYTICS (mescla query do destino + incoming_query por clique) ---
    $incomingGroupExpr = $hasIncomingQueryColumn ? "COALESCE(cl.incoming_query, '')" : "''";
    $qUtms = "SELECT l.original_url, $incomingGroupExpr AS incoming_query, COUNT(cl.id) AS total_clicks
              FROM clicks_log cl
              JOIN links l ON cl.link_id = l.id
              $campaignJoin
              $policyJoin
              WHERE l.user_id IN ($teamUserIdsStr) " . $linkFilter . $advancedFilter . $analyticsRetentionFilter . "
              GROUP BY l.original_url, $incomingGroupExpr";
    $stmt = $pdo->prepare($qUtms);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    if($campaignFilter) bindCampaignFilterParams($stmt, (string)$campaignFilter);
    bindAdvancedAnalyticsFilterParams($stmt, $hasFolderColumn ? $folderIdFilter : null, $hasTagMapTable ? $tagIdFilter : null, $subdomainFilter);
    $stmt->execute();
    $utmResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $utmSources = [];
    $utmMediums = [];
    $utmCampaigns = [];
    $organicClicks = 0;

    foreach ($utmResults as $row) {
        $incoming = $hasIncomingQueryColumn ? (string)($row['incoming_query'] ?? '') : '';
        $params = linkMergedAttributionParamsForClick((string)$row['original_url'], $incoming !== '' ? $incoming : null);
        $add = (int)$row['total_clicks'];
        linkAddUtmsFromParamsToBuckets($params, $add, $utmSources, $utmMediums, $utmCampaigns, $organicClicks);
    }

    if ($organicClicks > 0) {
        $utmSources['Orgânico / Direto'] = ($utmSources['Orgânico / Direto'] ?? 0) + $organicClicks;
    }

    // Formata e Ordena
    $formatUtm = function($data) {
        $res = [];
        foreach ($data as $l => $v) $res[] = ['label' => $l, 'value' => (int)$v];
        usort($res, function($a, $b) { return $b['value'] <=> $a['value']; });
        return $res;
    };

    $analyticsUtms = [
        'sources' => $formatUtm($utmSources),
        'mediums' => $formatUtm($utmMediums),
        'campaigns' => $formatUtm($utmCampaigns)
    ];

    // --- A/B TEST ANALYTICS ---
    $abTestData = null;
    if ($linkId) {
        $checkAb = $pdo->prepare("SELECT is_ab_test FROM links WHERE id = ?");
        $checkAb->execute([$linkId]);
        $linkInfo = $checkAb->fetch();
        if ($linkInfo && !empty($linkInfo['is_ab_test'])) {
            $qVariants = "
                SELECT 
                    lv.id, lv.name, lv.url, lv.weight, lv.status,
                    (SELECT COUNT(*) 
                     FROM clicks_log c2
                     JOIN links l2 ON l2.id = c2.link_id
                     WHERE c2.variant_id = lv.id 
                       AND c2.link_id = :linkId
                    ) as clicks
                FROM link_variants lv
                WHERE lv.link_id = :linkId
                ORDER BY lv.id ASC
            ";
            $vStmt = $pdo->prepare($qVariants);
            $vStmt->execute([':linkId' => $linkId]);
            $abTestData = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- CONVERSIONS ANALYTICS ---
    $conversionsData = null;
    if ($linkId) {
        $stmtC = $pdo->prepare("SELECT tracking_enabled FROM links WHERE id = ?");
        $stmtC->execute([$linkId]);
        $trkInfo = $stmtC->fetch();
        if ($trkInfo && !empty($trkInfo['tracking_enabled'])) {
            $qConv = "
                SELECT 
                    ca.id as action_id, ca.name as action_name, ca.ref_slug,
                    lv.id as variant_id, lv.name as variant_name,
                    COUNT(clog.id) as total_conversions
                FROM conversion_actions ca
                LEFT JOIN conversions_log clog ON clog.action_id = ca.id
                LEFT JOIN clicks_log c ON clog.click_id = c.id
                LEFT JOIN link_variants lv ON c.variant_id = lv.id
                WHERE ca.link_id = :linkId
                GROUP BY ca.id, lv.id
            ";
            $cStmt = $pdo->prepare($qConv);
            $cStmt->execute([':linkId' => $linkId]);
            $conversionsData = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $filterFolders = [];
    if ($hasFoldersTable) {
        $foldersStmt = $pdo->prepare("
            SELECT f.id, f.name, f.type
            FROM link_folders f
            WHERE f.user_id IN ($teamUserIdsStr)
              AND f.type = 'link'
            ORDER BY f.name ASC
        ");
        $foldersStmt->execute();
        $filterFolders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $filterTags = [];
    if ($hasTagsTable) {
        $tagsStmt = $pdo->prepare("
            SELECT t.id, t.name
            FROM link_tags t
            WHERE t.user_id IN ($teamUserIdsStr)
            ORDER BY t.name ASC
        ");
        $tagsStmt->execute();
        $filterTags = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $filterSubdomains = [];
    $subdomainsStmt = $pdo->prepare("
        SELECT DISTINCT l.subdomain
        FROM links l
        WHERE l.user_id IN ($teamUserIdsStr)
        ORDER BY l.subdomain ASC
    ");
    $subdomainsStmt->execute();
    foreach ($subdomainsStmt->fetchAll(PDO::FETCH_ASSOC) as $subdomainRow) {
        $value = trim((string)($subdomainRow['subdomain'] ?? ''));
        if ($value === '' || strtoupper($value) === 'ROOT') {
            continue;
        }
        $filterSubdomains[] = $value;
    }

    echo json_encode([
        'success' => true,
        'error_code' => null,
        'links' => $userLinks,
        'filter_folders' => $filterFolders,
        'filter_tags' => $filterTags,
        'filter_subdomains' => $filterSubdomains,
        'engagement' => $engagement,
        'locations' => $locations,
        'location_neighborhood_drilldown' => $hasGeoNeighborhoodColumn,
        'os' => $osData,
        'countries' => $countries,
        'referrers' => $referrers,
        'sources' => $sources,
        'utms' => $analyticsUtms,
        'devices' => $devices,
        'dwell' => $dwellInsights,
        'ab_test' => $abTestData,
        'conversions' => $conversionsData
    ]);

} catch (Throwable $e) {
    error_log('[analytics.php] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error_code' => 'QUERY_ERROR',
        'message' => 'Falha ao carregar analytics',
        'links' => [],
        'filter_folders' => [],
        'filter_tags' => [],
        'filter_subdomains' => [],
        'engagement' => [],
        'locations' => [],
        'os' => new stdClass(),
        'countries' => [],
        'referrers' => [],
        'sources' => [],
        'utms' => ['sources' => [], 'mediums' => [], 'campaigns' => []],
        'devices' => ['Mobile' => 0, 'Desktop' => 0],
        'dwell' => [
            'avg_seconds' => 0.0,
            'median_seconds' => 0.0,
            'valid_sessions' => 0,
            'discarded_sessions' => 0,
            'links_with_valid' => 0,
            'links_with_any_sessions' => 0,
            'series' => [],
            'valid_points' => []
        ],
        'location_neighborhood_drilldown' => false,
        'ab_test' => null,
        'conversions' => null
    ]);
}
?>