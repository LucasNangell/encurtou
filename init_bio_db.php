<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once 'db.php';

try {
    // 1. Create bio_pages if not exists
    $sql1 = "CREATE TABLE IF NOT EXISTS `bio_pages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `slug` varchar(100) NOT NULL,
      `title` varchar(150) DEFAULT NULL,
      `description` text DEFAULT NULL,
      `avatar_url` text DEFAULT NULL,
      `theme_bg_color` varchar(20) DEFAULT '#0f172a',
      `theme_btn_color` varchar(20) DEFAULT '#1A8CFF',
      `theme_btn_style` enum('solid','outline','rounded','pill') DEFAULT 'solid',
      `theme_text_color` varchar(20) DEFAULT '#ffffff',
      `whatsapp_number` varchar(20) DEFAULT NULL,
      `pix_key` varchar(150) DEFAULT NULL,
      `template_id` int(11) DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_slug` (`slug`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `bio_pages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql1);
    echo "Table bio_pages checked/created.\n";

    // 2. Create bio_links if not exists
    $sql2 = "CREATE TABLE IF NOT EXISTS `bio_links` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `bio_page_id` int(11) NOT NULL,
      `url` text NOT NULL,
      `title` varchar(150) NOT NULL,
      `icon_class` varchar(50) DEFAULT NULL,
      `is_featured` tinyint(1) DEFAULT 0,
      `display_order` int(11) DEFAULT 0,
      PRIMARY KEY (`id`),
      KEY `bio_page_id` (`bio_page_id`),
      CONSTRAINT `bio_links_ibfk_1` FOREIGN KEY (`bio_page_id`) REFERENCES `bio_pages` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql2);
    echo "Table bio_links checked/created.\n";

    // 3. Run migration for new columns
    try {
        $sql3 = "ALTER TABLE `bio_links` 
                ADD COLUMN `link_type` ENUM('link', 'social', 'whatsapp', 'pix') DEFAULT 'link' AFTER `url`,
                ADD COLUMN `social_network` VARCHAR(50) DEFAULT NULL AFTER `link_type`,
                ADD COLUMN `social_value` TEXT DEFAULT NULL AFTER `social_network`;";
        $pdo->exec($sql3);
        echo "Migration applied: new columns added to bio_links.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Migration columns already exist.\n";
        } else {
            throw $e;
        }
    }

    $pageMigrations = [
        "ALTER TABLE `bio_pages` ADD COLUMN `page_type` VARCHAR(30) DEFAULT 'bio' AFTER `template_id`",
        "ALTER TABLE `bio_pages` ADD COLUMN `page_status` VARCHAR(20) DEFAULT 'published' AFTER `page_type`",
        "ALTER TABLE `bio_pages` ADD COLUMN `selected_goal_key` VARCHAR(80) DEFAULT NULL AFTER `page_status`",
        "ALTER TABLE `bio_pages` ADD COLUMN `builder_schema_json` LONGTEXT DEFAULT NULL AFTER `selected_goal_key`"
    ];

    foreach ($pageMigrations as $migration) {
        try {
            $pdo->exec($migration);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
        }
    }

    echo "Bio builder columns checked/created.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
