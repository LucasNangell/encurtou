<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once 'db.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    echo "Tables in database:\n";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
