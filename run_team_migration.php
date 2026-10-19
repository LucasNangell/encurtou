<?php
// Hardcoding local credentials to bypass CLI $_SERVER check in db.php
$host = 'localhost';
$dbname = 'u456568047_encurtador_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents('team_update_migration.sql');
    $pdo->exec($sql);
    
    echo "Migração de equipes concluída com sucesso!";
} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage();
}
