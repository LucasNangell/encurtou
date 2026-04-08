<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o servidor é 'localhost' ou '127.0.0.1'
$isLocal = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $_SERVER['SERVER_NAME'] == 'localhost';

if ($isLocal) {
    // --- CONFIGURAÇÕES LOCAIS (XAMPP, WAMP, Laragon) ---
    $host = 'localhost';
    $dbname = 'u456568047_encurtador_db'; // Nome que você deu ao banco no phpMyAdmin local
    $user = 'root';            // Padrão do XAMPP/WAMP é root
    $pass = '';                // Padrão do XAMPP/WAMP é vazio
} else {
    // --- CONFIGURAÇÕES DO SERVIDOR (Hostinger) ---
    $host = 'localhost';
    $dbname = 'u456568047_encurtador_db'; // Nome completo que a Hostinger gera
    $user = 'u456568047_admin';           // Usuário completo que a Hostinger gera
    $pass = 'CreativeStudio@Nangell2026';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // No local, é bom ver o erro real para corrigir rápido
    die("Erro de conexão: " . $e->getMessage());
}

function getRealIP() {
    // Se estiver no local, Cloudflare não existe, então pega o IP padrão
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        return $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
?>