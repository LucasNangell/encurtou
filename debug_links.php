<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once 'c:/xampp/htdocs/Encurtou/db.php';

$stmt = $pdo->query("SELECT id, path, original_url, url_ios, url_android, url_windows FROM links ORDER BY id DESC LIMIT 5");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($links, JSON_PRETTY_PRINT);
?>
