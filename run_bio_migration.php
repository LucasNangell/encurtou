<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once 'db.php';

try {
    $sql = "ALTER TABLE `bio_links` 
            ADD COLUMN `link_type` ENUM('link', 'social', 'whatsapp', 'pix') DEFAULT 'link' AFTER `url`,
            ADD COLUMN `social_network` VARCHAR(50) DEFAULT NULL AFTER `link_type`,
            ADD COLUMN `social_value` TEXT DEFAULT NULL AFTER `social_network`;";
    
    $pdo->exec($sql);
    echo "Migration completed successfully. New columns added to bio_links.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist in bio_links.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
