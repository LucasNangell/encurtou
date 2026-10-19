<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once 'db.php';

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    if (!columnExists($pdo, 'users', 'is_banned')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_banned` TINYINT(1) NOT NULL DEFAULT 0");
        echo "Added users.is_banned\n";
    } else {
        echo "users.is_banned already exists\n";
    }

    if (!columnExists($pdo, 'users', 'ban_expires_at')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `ban_expires_at` DATETIME NULL DEFAULT NULL");
        echo "Added users.ban_expires_at\n";
    } else {
        echo "users.ban_expires_at already exists\n";
    }

    if (!columnExists($pdo, 'users', 'ban_reason')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `ban_reason` VARCHAR(500) NULL DEFAULT NULL");
        echo "Added users.ban_reason\n";
    } else {
        echo "users.ban_reason already exists\n";
    }

    if (!columnExists($pdo, 'users', 'banned_by')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `banned_by` INT(11) NULL DEFAULT NULL");
        echo "Added users.banned_by\n";
    } else {
        echo "users.banned_by already exists\n";
    }

    if (!columnExists($pdo, 'users', 'banned_at')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `banned_at` DATETIME NULL DEFAULT NULL");
        echo "Added users.banned_at\n";
    } else {
        echo "users.banned_at already exists\n";
    }

    if (!columnExists($pdo, 'links', 'admin_ban_paused')) {
        $pdo->exec("ALTER TABLE `links` ADD COLUMN `admin_ban_paused` TINYINT(1) NOT NULL DEFAULT 0");
        echo "Added links.admin_ban_paused\n";
    } else {
        echo "links.admin_ban_paused already exists\n";
    }

    echo "User ban migration completed.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
