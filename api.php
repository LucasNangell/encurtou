<?php
header('Content-Type: application/json');
require_once 'db.php';

function get_page_title($url) {
    if (!$url) return 'Página sem título';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita erro de SSL em alguns servidores

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$html) return 'Página sem título';

    // Tenta capturar a tag <title>
    if (preg_match("/<title[^>]*>(.*?)<\/title>/is", $html, $matches)) {
        $title = trim($matches[1]);
        // Limpeza básica e conversão de encoding se necessário
        return mb_convert_encoding($title, 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252');
    }
    
    return 'Página sem título';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_slug') {
    $path = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_GET['slug'] ?? '')));
    $sub = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_GET['subdomain'] ?? '')));
    $subValue = $sub ?: 'ROOT';

    if (!$path) {
        echo json_encode(['available' => true]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM links WHERE subdomain = ? AND path = ?");
    $stmt->execute([$subValue, $path]);
    $exists = $stmt->fetch();

    echo json_encode(['available' => !$exists]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VERIFICA SE ESTÁ LOGADO
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Faça login para criar links.']);
        exit;
    }

    $url = filter_var($_POST['url'], FILTER_VALIDATE_URL);
    $sub = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['subdomain'])));
    $path = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['path'])));

    if (!$url || !$path) {
        echo json_encode(['success' => false, 'message' => 'URL e Terminação são obrigatórios.']);
        exit;
    }

    $subValue = $sub ?: 'ROOT';
    $creatorIP = getRealIP();
    $pageTitle = get_page_title($url);
    $type = (isset($_POST['type']) && $_POST['type'] === 'qr') ? 'qr' : 'link';

    try {
        $stmt = $pdo->prepare("INSERT INTO links (user_id, subdomain, path, original_url, title, creator_ip, type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $subValue, $path, $url, $pageTitle, $creatorIP, $type]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { 
            echo json_encode(['success' => false, 'message' => 'Esta terminação já está em uso neste subdomínio.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar.']);
        }
    }
}
?>