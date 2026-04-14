<?php
require_once 'db.php';

// 1. Identificar o Host e capturar Slug/Subdomínio injetados (Via Cloudflare Worker)
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\d+$/', '', $host); // Remover porta se existir

$baseDomain = 'encurtou.pro';

// Captura a Slug (Prioridade para o GET, que o Cloudflare Worker usará)
$slug = isset($_GET['slug']) ? strtolower(trim($_GET['slug'], '/')) : '';

// Captura o Subdomínio (Prioridade para o GET injetado pelo Worker)
$sub = (isset($_GET['subdomain']) && !empty($_GET['subdomain'])) 
    ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['subdomain'])) 
    : 'ROOT';

// Fallback: Se o subdomínio não veio via GET, tenta extrair do Host (Acesso direto)
if ($sub === 'ROOT' && $host !== 'localhost' && $host !== '127.0.0.1') {
    $domainSuffix = '.' . $baseDomain;
    if (substr($host, -strlen($domainSuffix)) === $domainSuffix) {
        $prefix = substr($host, 0, -strlen($domainSuffix));
        $parts = explode('.', $prefix);
        if (isset($parts[0]) && $parts[0] === 'www') {
            array_shift($parts);
        }
        $sub = (!empty($parts) && $parts[0] !== '') ? $parts[0] : 'ROOT';
    }
}

// 3. Se não houver slug, carrega a Home/Dashboard
if (empty($slug)) {
    include('index.html');
    exit;
}

try {
    // 4. Buscar o link no Banco de Dados
    // Combinamos o subdomínio e o path (slug)
    $stmt = $pdo->prepare("SELECT * FROM links WHERE subdomain = ? AND path = ?");
    $stmt->execute([$sub, $slug]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        // TRAVA: Verifica se o link está pausado
        if ($link['status'] === 'paused') {
            die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif; color:#64748b;'>Este link está temporariamente indisponível.</h2>");
        }

        $linkId = $link['id'];
        $visitorIp = getRealIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'Acesso Direto';
        $country = $_SERVER["HTTP_CF_IPCOUNTRY"] ?? 'Desconhecido';

        // 5. Registrar Estatísticas
        $update = $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?");
        $update->execute([$linkId]);

        $log = $pdo->prepare("INSERT INTO clicks_log (link_id, visitor_ip, visitor_country, user_agent, referer, source) VALUES (?, ?, ?, ?, ?, ?)");
        $source = (isset($_GET['src']) && $_GET['src'] === 'qr') ? 'qr' : 'digital';
        @$log->execute([$linkId, $visitorIp, $country, $userAgent, $referer, $source]);

        // 6. Lógica de Redirecionamento por Dispositivo
        $destination = $link['original_url'];
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Safely check for columns (in case they don't exist yet in the DB)
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            if (!empty($link['url_ios'])) $destination = $link['url_ios'];
        } elseif (preg_match('/Android/i', $ua)) {
            if (!empty($link['url_android'])) $destination = $link['url_android'];
        } elseif (preg_match('/Windows/i', $ua)) {
            if (!empty($link['url_windows'])) $destination = $link['url_windows'];
        }

        // MODO DEBUG (Apenas se ?debug=1 for passado na URL)
        if (isset($_GET['debug'])) {
            echo "<h3>Debug de Redirecionamento</h3>";
            echo "<b>Host:</b> " . htmlspecialchars($host) . "<br>";
            echo "<b>Subdomínio Detectado:</b> " . htmlspecialchars($sub) . "<br>";
            echo "<b>Slug:</b> " . htmlspecialchars($slug) . "<br><br>";
            echo "<b>User Agent:</b> " . htmlspecialchars($ua) . "<br>";
            echo "<b>Destino Final:</b> " . htmlspecialchars($destination) . "<br>";
            exit;
        }

        // 7. Redirecionamento Final
        header("Location: " . $destination, true, 302);
        exit;
    } else {
        // Caso o link não exista no banco
        http_response_code(404);
        die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Ops! Link não encontrado.</h2>");
    }

} catch (PDOException $e) {
    // Logar erro e exibir mensagem genérica
    error_log("Erro no Redirect: " . $e->getMessage());
    die("Houve um erro ao processar o redirecionamento.");
}
?>