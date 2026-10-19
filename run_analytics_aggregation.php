<?php
require_once 'db.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only";
    exit;
}

try {
    $daysBack = (int)($argv[1] ?? 2);
    if ($daysBack < 1 || $daysBack > 30) $daysBack = 2;

    $sql = "
        INSERT INTO analytics_daily_agg (user_id, agg_date, link_id, utm_campaign, source, clicks_total, conversions_total)
        SELECT
            l.user_id,
            DATE(cl.clicked_at) AS agg_date,
            l.id AS link_id,
            NULLIF(
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX(CONCAT(l.original_url, '&'), 'utm_campaign=', -1),
                    '&',
                    1
                ),
                CONCAT(l.original_url, '&')
            ) AS utm_campaign,
            cl.source,
            COUNT(cl.id) AS clicks_total,
            COUNT(cv.id) AS conversions_total
        FROM clicks_log cl
        JOIN links l ON l.id = cl.link_id
        LEFT JOIN conversions_log cv ON cv.click_id = cl.id
        WHERE cl.clicked_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY l.user_id, DATE(cl.clicked_at), l.id, utm_campaign, cl.source
        ON DUPLICATE KEY UPDATE
            clicks_total = VALUES(clicks_total),
            conversions_total = VALUES(conversions_total),
            updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':days' => $daysBack]);

    echo "OK: analytics_daily_agg atualizado.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
