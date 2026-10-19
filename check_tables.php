<?php
require 'db.php';
$stmt = $pdo->query("SHOW TABLES");
echo "Tabelas no banco:\n";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
