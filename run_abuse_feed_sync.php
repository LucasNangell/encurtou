<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/abuse_policy_helpers.php';

$isCli = (php_sapi_name() === 'cli');
$inserted = 0;

function abuseFeedUpsert(PDO $pdo, string $url, string $source): int
{
    if (!abusePolicyTableExists($pdo, 'abuse_feed_entries')) {
        return 0;
    }
    $normalized = abuseNormalizeUrl($url);
    if (!preg_match('#^https?://#i', $normalized)) {
        return 0;
    }
    $hash = abuseUrlHash($normalized);
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO abuse_feed_entries (url_hash, url_normalized, source, added_at) VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$hash, $normalized, $source]);
    return (int)$stmt->rowCount();
}

function abuseFeedFetchLines(string $url, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'linkneo-pro-abuse-sync/1.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        return [];
    }
    return preg_split('/\r\n|\r|\n/', $body) ?: [];
}

$feeds = [
    'openphish' => 'https://openphish.com/feed.txt',
    'urlhaus' => 'https://urlhaus.abuse.ch/downloads/text_online/',
];

foreach ($feeds as $source => $feedUrl) {
    foreach (abuseFeedFetchLines($feedUrl) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!preg_match('#^https?://#i', $line)) {
            continue;
        }
        $inserted += abuseFeedUpsert($pdo, $line, $source);
    }
}

$summary = "Abuse feed sync: inserted=$inserted\n";
if ($isCli) {
    echo $summary;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $summary;
}
