<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AbusePolicyService.php';
require_once __DIR__ . '/abuse_policy_helpers.php';
require_once __DIR__ . '/edge_cache_helpers.php';

$isCli = (php_sapi_name() === 'cli');
$limit = 500;
if ($isCli && isset($argv[1]) && ctype_digit($argv[1])) {
    $limit = max(1, (int)$argv[1]);
}

$paused = 0;
$scanned = 0;

function abuseRescanCollectUrls(PDO $pdo, int $limit): array
{
    $urls = [];
    $stmt = $pdo->query(
        "SELECT id, original_url FROM links WHERE status = 'active' ORDER BY id DESC LIMIT " . (int)$limit
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $urls[] = ['type' => 'link', 'id' => (int)$row['id'], 'url' => (string)$row['original_url']];
    }

    if (abusePolicyTableExists($pdo, 'link_variants')) {
        $vStmt = $pdo->query(
            "SELECT lv.id, lv.link_id, lv.url FROM link_variants lv
             INNER JOIN links l ON l.id = lv.link_id AND l.status = 'active'
             WHERE lv.status = 'active' LIMIT " . (int)$limit
        );
        while ($row = $vStmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = ['type' => 'variant', 'id' => (int)$row['link_id'], 'url' => (string)$row['url']];
        }
    }

    if (abusePolicyTableExists($pdo, 'bio_links')) {
        $bStmt = $pdo->query(
            "SELECT bl.id, bl.bio_page_id, bl.url FROM bio_links bl
             INNER JOIN bio_pages bp ON bp.id = bl.bio_page_id
             WHERE bl.url LIKE 'http%' LIMIT " . (int)$limit
        );
        while ($row = $bStmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = ['type' => 'bio', 'id' => (int)$row['bio_page_id'], 'url' => (string)$row['url']];
        }
    }

    return $urls;
}

foreach (abuseRescanCollectUrls($pdo, $limit) as $item) {
    $url = trim($item['url']);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        continue;
    }
    $scanned++;
    $scan = AbusePolicyService::scanUrlWithCache($pdo, $url, false);
    if (($scan['verdict'] ?? '') !== 'blocked') {
        continue;
    }
    if ($item['type'] === 'link' || $item['type'] === 'variant') {
        AbusePolicyService::pauseLinkForSecurity($pdo, (int)$item['id'], 'rescan_blocked', $scan);
        if (function_exists('edgeCacheInvalidateLinkById')) {
            try {
                edgeCacheInvalidateLinkById($pdo, (int)$item['id'], 'security_rescan', 'run_abuse_rescan');
            } catch (Throwable $e) {
            }
        }
    }
    $paused++;
}

$summary = "Abuse rescan: scanned=$scanned paused=$paused\n";
if ($isCli) {
    echo $summary;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $summary;
}

if ($paused > 0) {
    abuseNotifyAdmin($pdo, '[linkneo.pro] Links pausados no rescan', '<p>' . (int)$paused . ' link(s) pausado(s) no rescan de segurança.</p>');
}
