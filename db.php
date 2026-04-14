<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLocal = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || ($_SERVER['SERVER_NAME'] ?? '') == 'localhost' || strpos(($_SERVER['HTTP_HOST'] ?? ''), 'localhost') !== false;

if ($isLocal) {
    $host = 'localhost';
    $dbname = 'u456568047_encurtador_db'; // Sugestão: Nome simplificado para local
    $user = 'root';
    $pass = '';
} else {
    $host = 'localhost';
    $dbname = 'u456568047_encurtador_db';
    $user = 'u456568047_admin';
    $pass = 'CreativeStudio@Nangell2026';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()]));
}

function getRealIP() {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        return $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}