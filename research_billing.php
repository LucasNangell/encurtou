<?php
require 'db.php';
$stmt = $pdo->query("SHOW TABLES");
echo "Tabelas informadas no banco:\n";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

echo "\nColunas da tabela 'users':\n";
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nConteúdo da tabela 'plans':\n";
$stmt = $pdo->query("SELECT * FROM plans");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
