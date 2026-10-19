<?php
require 'db.php';
$stmt = $pdo->query("SELECT * FROM plans");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
