<?php
require_once 'db.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only";
    exit;
}

try {
    $sql = "
        DELETE cl
        FROM clicks_log cl
        JOIN links l ON l.id = cl.link_id
        JOIN users u ON u.id = l.user_id
        LEFT JOIN user_account_overrides o ON o.user_id = u.id AND o.custom_plan_id IS NOT NULL
        LEFT JOIN plans p ON p.id = u.current_plan_id
        LEFT JOIN plans op ON op.id = o.custom_plan_id
        WHERE cl.clicked_at < DATE_SUB(
            NOW(),
            INTERVAL COALESCE(
                NULLIF(op.analytics_days, 0),
                NULLIF(p.analytics_days, 0),
                CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(op.features_json, p.features_json, '{}'), '$.analytics_days')) AS UNSIGNED),
                CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(op.features_json, p.features_json, '{}'), '$.analytics_retention_days')) AS UNSIGNED),
                0
            ) DAY
        )
        AND COALESCE(
            NULLIF(op.analytics_days, 0),
            NULLIF(p.analytics_days, 0),
            CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(op.features_json, p.features_json, '{}'), '$.analytics_days')) AS UNSIGNED),
            CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(op.features_json, p.features_json, '{}'), '$.analytics_retention_days')) AS UNSIGNED),
            0
        ) > 0
    ";

    $deleted = $pdo->exec($sql);
    echo "OK: limpeza de retenção concluída. Removidos: " . (int)$deleted . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
