<?php
require_once __DIR__ . '/config_runtime.php';

function envOrDefault(string $key, string $default = ''): string
{
    $value = $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function isLocalEnvironment(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost'
        || strpos(($_SERVER['HTTP_HOST'] ?? ''), 'localhost') !== false
        || php_sapi_name() === 'cli';
}

function isHttpsRequest(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';
    if (!empty($https) && strtolower($https) !== 'off') {
        return true;
    }
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (!empty($xfp) && strtolower($xfp) === 'https') {
        return true;
    }
    return false;
}

function startHardenedSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isLocal = isLocalEnvironment();
    $secure = !$isLocal && isHttpsRequest();

    // Session hardening (best-effort; some hosts may override ini)
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_secure', $secure ? '1' : '0');
    @ini_set('session.sid_length', '48');
    @ini_set('session.sid_bits_per_character', '6');

    // SameSite via cookie params
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_name(envOrDefault('SESSION_NAME', 'ENCURTOUSESSID'));
    session_start();
}

startHardenedSession();

$user = envOrDefault('DB_USER', 'root');
$pass = envOrDefault('DB_PASS', '');
$host = envOrDefault('DB_HOST', 'localhost');
$dbname = envOrDefault('DB_NAME', 'encurtador_db');

// Se estamos em produção (tem senha no env) e falhou algo, loga.
if (!isLocalEnvironment() && empty($pass) && $user !== 'root') {
    error_log('Database credentials missing in environment.');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Erro interno de conexão.']));
}

function getRealIP()
{
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Lista simplificada de IPs (IPv4) da Cloudflare - para uso em verificação rigorosa deve ser mantida atualizada
    // https://www.cloudflare.com/ips-v4
    $cfIps = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22'
    ];

    $isCf = false;
    foreach ($cfIps as $cfIp) {
        if (ip_in_range($remoteIp, $cfIp)) {
            $isCf = true;
            break;
        }
    }

    if ($isCf && isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $remoteIp;
}

function ip_in_range($ip, $range) {
    if (strpos($range, '/') == false) {
        $range .= '/32';
    }
    list($range, $netmask) = explode('/', $range, 2);
    $range_decimal = ip2long($range);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~ $wildcard_decimal;
    return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
}

function csrfEnforced(): bool
{
    // Fail-closed por padrão em produção; desative explicitamente apenas em ambiente de desenvolvimento.
    return envOrDefault('CSRF_ENFORCE', '1') === '1';
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function requireCsrfToken(bool $authenticatedOnly = true): void
{
    if (!csrfEnforced()) {
        // Still ensure token exists for the frontend to start sending it.
        getCsrfToken();
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    if ($authenticatedOnly && !isset($_SESSION['user_id'])) {
        return;
    }

    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $expected = getCsrfToken();
    if (is_string($provided) && $provided !== '' && hash_equals($expected, $provided)) {
        return;
    }

    // Fluxos públicos (sem sessão autenticada): fallback para validação de
    // mesma origem quando a sessão não persiste em hospedagens compartilhadas.
    if (!$authenticatedOnly) {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $origin = strtolower((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        $referer = strtolower((string)($_SERVER['HTTP_REFERER'] ?? ''));

        $isSameOrigin = false;
        if ($origin !== '') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $isSameOrigin = is_string($originHost) && strtolower($originHost) === $host;
        } elseif ($referer !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $isSameOrigin = is_string($refererHost) && strtolower($refererHost) === $host;
        }

        if ($isSameOrigin) {
            return;
        }
    }

    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token inválido ou ausente.']);
    exit;
}

function pdoColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . "\0" . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}