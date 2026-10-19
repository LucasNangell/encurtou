<?php
require_once 'db.php';
header('Content-Type: application/json');

function trackAllowedOrigins(): array {
    $raw = trim((string)envOrDefault('TRACK_ALLOWED_ORIGINS', ''));
    if ($raw === '') {
        return [];
    }
    $items = array_filter(array_map('trim', explode(',', $raw)));
    $allowed = [];
    foreach ($items as $item) {
        $parts = parse_url($item);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            continue;
        }
        $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }
        $allowed[$origin] = true;
    }
    return $allowed;
}

function trackOriginAllowed(string $origin, array $allowedOrigins): bool {
    $parts = parse_url($origin);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    if (!empty($parts['user']) || !empty($parts['pass'])) {
        return false;
    }

    $scheme = strtolower((string)$parts['scheme']);
    $host = strtolower((string)$parts['host']);
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    $normalized = $scheme . '://' . $host . $port;

    if (!empty($allowedOrigins)) {
        return isset($allowedOrigins[$normalized]);
    }

    // Compatibilidade: sem allowlist explícita, permite origem HTTPS.
    // Permite HTTP apenas em localhost para ambiente local.
    if ($scheme === 'https') {
        return true;
    }
    if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)) {
        return true;
    }
    return false;
}

function trackApplyCorsHeaders(string $origin): bool {
    $allowed = trackAllowedOrigins();
    $isAllowed = ($origin === '') ? true : trackOriginAllowed($origin, $allowed);

    if ($origin !== '' && $isAllowed) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Track-Token');
    return $isAllowed;
}

function trackCheckRateLimit(PDO $pdo, string $ip): bool {
    $maxPerMinute = (int)envOrDefault('TRACK_RATE_LIMIT_PER_MIN', '120');
    $maxPerMinute = max(20, min(1000, $maxPerMinute));
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS track_rate_limits (
            ip VARCHAR(45) NOT NULL PRIMARY KEY,
            window_start DATETIME NOT NULL,
            hits INT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare("SELECT window_start, hits FROM track_rate_limits WHERE ip = ? LIMIT 1");
        $stmt->execute([$ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $ins = $pdo->prepare("INSERT INTO track_rate_limits (ip, window_start, hits) VALUES (?, NOW(), 1)");
            $ins->execute([$ip]);
            return true;
        }

        $windowStart = strtotime((string)$row['window_start']);
        $now = time();
        if ($windowStart === false || ($now - $windowStart) >= 60) {
            $upd = $pdo->prepare("UPDATE track_rate_limits SET window_start = NOW(), hits = 1 WHERE ip = ?");
            $upd->execute([$ip]);
            return true;
        }

        $hits = (int)($row['hits'] ?? 0);
        if ($hits >= $maxPerMinute) {
            return false;
        }
        $inc = $pdo->prepare("UPDATE track_rate_limits SET hits = hits + 1 WHERE ip = ?");
        $inc->execute([$ip]);
        return true;
    } catch (Throwable $e) {
        error_log('track_rate_limit_error: ' . $e->getMessage());
        // Fail-open para não interromper conversões em caso de falha de infra.
        return true;
    }
}

function trackSanitizeMetadata($metadata): ?string {
    if (!is_array($metadata)) {
        return null;
    }
    $clean = [];
    $count = 0;
    foreach ($metadata as $key => $value) {
        if ($count >= 30) {
            break;
        }
        $safeKey = mb_substr(trim((string)$key), 0, 80);
        if ($safeKey === '') {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $safeValue = mb_substr((string)$value, 0, 500);
            $clean[$safeKey] = $safeValue;
            $count++;
        }
    }
    if (empty($clean)) {
        return null;
    }
    $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : null;
}

$origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
$originAllowed = trackApplyCorsHeaders($origin);
if ($origin !== '' && !$originAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origem não permitida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Receber JSON POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Payload inválido']);
    exit;
}

$ep_cid = $data['ep_cid'] ?? null;
$action_slug = $data['action'] ?? null;
$metadata = $data['metadata'] ?? [];

if (!$ep_cid || !$action_slug) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros ep_cid e action obrigatórios']);
    exit;
}

if (!preg_match('/^[a-f0-9-]{16,64}$/i', (string)$ep_cid)) {
    echo json_encode(['success' => false, 'message' => 'ep_cid inválido']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', (string)$action_slug)) {
    echo json_encode(['success' => false, 'message' => 'action inválida']);
    exit;
}

$trackTokenExpected = trim((string)envOrDefault('TRACK_INGEST_TOKEN', ''));
if ($trackTokenExpected !== '') {
    $sent = trim((string)($_SERVER['HTTP_X_TRACK_TOKEN'] ?? ($data['track_token'] ?? '')));
    if ($sent === '' || !hash_equals($trackTokenExpected, $sent)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autorizado']);
        exit;
    }
}

if (!trackCheckRateLimit($pdo, getRealIP())) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Limite de requisições atingido']);
    exit;
}

try {
    // Busca o log do clique pelo ep_cid
    $stmtClick = $pdo->prepare("SELECT id, link_id FROM clicks_log WHERE click_uid = ?");
    $stmtClick->execute([$ep_cid]);
    $click = $stmtClick->fetch(PDO::FETCH_ASSOC);

    if (!$click) {
        // UID não encontrado (pode ser teste ou fake), descarta silenciosamente
        echo json_encode(['success' => true, 'status' => 'ignored']);
        exit;
    }

    // Busca a action pelo slug e vinculada ao mesmo link_id
    $stmtAction = $pdo->prepare("SELECT id FROM conversion_actions WHERE link_id = ? AND ref_slug = ? AND is_active = 1");
    $stmtAction->execute([$click['link_id'], $action_slug]);
    $action = $stmtAction->fetch(PDO::FETCH_ASSOC);

    if (!$action) {
        echo json_encode(['success' => true, 'status' => 'action_not_found_or_inactive']);
        exit;
    }

    // Verifica dupla conversão para o mesmo clique + ação na mesma hora (opcional)
    // Para simplificar, insere todas como log separado (pode agrupar em interface analytics)

    // Insere o evento de conversão
    $metaJson = trackSanitizeMetadata($metadata);
    $ins = $pdo->prepare("INSERT INTO conversions_log (click_id, action_id, metadata) VALUES (?, ?, ?)");
    $ins->execute([$click['id'], $action['id'], $metaJson]);

    echo json_encode(['success' => true, 'status' => 'recorded']);

} catch (PDOException $e) {
    // Silencia erros do banco para público, mas pode falhar se a migrate não rodou
    echo json_encode(['success' => false, 'status' => 'error_db']);
}
?>
