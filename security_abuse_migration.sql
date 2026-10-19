-- Security & anti-abuse schema for linkneo.pro

-- Users: session invalidation, email verification, abuse strikes, signup tracking
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `session_invalidated_at` DATETIME NULL DEFAULT NULL AFTER `banned_at`,
    ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `email_verify_token_hash` VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `email_verify_sent_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `abuse_strike_count` INT(11) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `abuse_strike_window_start` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `signup_ip` VARCHAR(45) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `signup_user_agent` VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `can_moderate` TINYINT(1) NOT NULL DEFAULT 0;

-- Links: security pause flags
ALTER TABLE `links`
    ADD COLUMN IF NOT EXISTS `security_paused` TINYINT(1) NOT NULL DEFAULT 0 AFTER `admin_ban_paused`,
    ADD COLUMN IF NOT EXISTS `security_verdict` VARCHAR(32) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `security_scanned_at` DATETIME NULL DEFAULT NULL;

-- Bio pages: admin ban pause flag
ALTER TABLE `bio_pages`
    ADD COLUMN IF NOT EXISTS `admin_ban_paused` TINYINT(1) NOT NULL DEFAULT 0;

-- Keyword blacklist rules
CREATE TABLE IF NOT EXISTS `abuse_keyword_rules` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `term` VARCHAR(100) NOT NULL,
    `rule_type` ENUM('slug','subdomain','destination','any') NOT NULL DEFAULT 'any',
    `severity` ENUM('block','warn') NOT NULL DEFAULT 'block',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active_type` (`is_active`, `rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Domain blocklist
CREATE TABLE IF NOT EXISTS `abuse_domain_blocklist` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `pattern` VARCHAR(255) NOT NULL,
    `match_type` ENUM('exact','suffix','destination_path') NOT NULL DEFAULT 'exact',
    `reason` VARCHAR(255) NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- URL scan cache
CREATE TABLE IF NOT EXISTS `url_scan_cache` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `url_hash` CHAR(64) NOT NULL,
    `url_normalized` TEXT NOT NULL,
    `verdict` ENUM('safe','blocked','unknown') NOT NULL DEFAULT 'unknown',
    `sources` JSON NULL,
    `scanned_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_url_hash` (`url_hash`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- External feed entries
CREATE TABLE IF NOT EXISTS `abuse_feed_entries` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `url_hash` CHAR(64) NOT NULL,
    `url_normalized` TEXT NOT NULL,
    `source` VARCHAR(50) NOT NULL,
    `added_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_feed_hash_source` (`url_hash`, `source`),
    KEY `idx_url_hash` (`url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public abuse reports
CREATE TABLE IF NOT EXISTS `abuse_reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `link_id` INT(11) NULL DEFAULT NULL,
    `bio_page_id` INT(11) NULL DEFAULT NULL,
    `reported_slug` VARCHAR(100) NULL DEFAULT NULL,
    `reported_subdomain` VARCHAR(50) NULL DEFAULT NULL,
    `reporter_ip` VARCHAR(45) NULL DEFAULT NULL,
    `reporter_email` VARCHAR(255) NULL DEFAULT NULL,
    `reason` TEXT NULL,
    `status` ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `reviewed_by` INT(11) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_link` (`link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Security audit log
CREATE TABLE IF NOT EXISTS `security_audit_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(64) NOT NULL,
    `user_id` INT(11) NULL DEFAULT NULL,
    `link_id` INT(11) NULL DEFAULT NULL,
    `details_json` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_created` (`event_type`, `created_at`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
