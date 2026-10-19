<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$token = trim((string)($_SERVER['HTTP_X_HEALTHCHECK_TOKEN'] ?? ($_GET['token'] ?? '')));
$expected = envOrDefault('HEALTHCHECK_TOKEN', '');

if ($expected === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Healthcheck indisponível.']);
    exit;
}

if ($token === '' || !hash_equals($expected, $token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

function healthcheckDbOk(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query('SELECT 1');
        return (int)$stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        error_log('Healthcheck DB ping failed: ' . $e->getMessage());
        return false;
    }
}

$checks = [
    'runtime' => [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'openssl' => extension_loaded('openssl'),
        'mbstring' => extension_loaded('mbstring'),
        'json' => extension_loaded('json'),
        'getallheaders' => function_exists('getallheaders'),
    ],
    'database' => [
        'credentials_present' => envOrDefault('DB_HOST', '') !== ''
            && envOrDefault('DB_NAME', '') !== ''
            && envOrDefault('DB_USER', '') !== ''
            && envOrDefault('DB_PASS', '') !== '',
        'connection' => healthcheckDbOk($pdo),
    ],
];

$ok = true;
foreach ($checks as $key => $value) {
    if (is_array($value)) {
        foreach ($value as $nested) {
            if ($nested === false) {
                $ok = false;
                break 2;
            }
        }
    } elseif ($value === false) {
        $ok = false;
        break;
    }
}

http_response_code($ok ? 200 : 503);
echo json_encode([
    'success' => $ok,
    'status' => $ok ? 'ok' : 'degraded',
    'checks' => $checks,
    'timestamp' => date('c'),
], JSON_UNESCAPED_UNICODE);
