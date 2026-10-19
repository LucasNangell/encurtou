<?php
$host = 'localhost';
$dbname = 'u456568047_encurtador_db';
$user = 'root';
$pass = '';

$sql = file_get_contents(__DIR__ . '/founder_monthly_migration.sql');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec($sql);
    echo "Migração Founder mensal concluída com sucesso!";
} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage();
}
