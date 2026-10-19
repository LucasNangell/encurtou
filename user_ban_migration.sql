-- User ban columns on users table
ALTER TABLE `users`
    ADD COLUMN `is_banned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `created_at`,
    ADD COLUMN `ban_expires_at` DATETIME NULL DEFAULT NULL AFTER `is_banned`,
    ADD COLUMN `ban_reason` VARCHAR(500) NULL DEFAULT NULL AFTER `ban_expires_at`,
    ADD COLUMN `banned_by` INT(11) NULL DEFAULT NULL AFTER `ban_reason`,
    ADD COLUMN `banned_at` DATETIME NULL DEFAULT NULL AFTER `banned_by`;

-- Flag on links paused by admin ban (restore only these on unban)
ALTER TABLE `links`
    ADD COLUMN `admin_ban_paused` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;
