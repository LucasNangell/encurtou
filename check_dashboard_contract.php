<?php
require_once 'db.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only";
    exit;
}

$userId = (int)($argv[1] ?? 0);
if ($userId <= 0) {
    fwrite(STDERR, "Uso: php check_dashboard_contract.php <user_id>\n");
    exit(1);
}

$_SESSION['user_id'] = $userId;
$_GET['version'] = '2';
$_GET['period'] = '14';

ob_start();
include __DIR__ . '/dashboard.php';
$raw = ob_get_clean();
$json = json_decode((string)$raw, true);

$requiredKeys = [
    'success',
    'kpis',
    'insights',
    'charts',
    'recent_activity'
];

$missing = [];
foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $json ?? [])) {
        $missing[] = $key;
    }
}

if (!empty($missing)) {
    fwrite(STDERR, "Falha de contrato. Chaves ausentes: " . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo "OK: contrato do dashboard_v2 válido.\n";
