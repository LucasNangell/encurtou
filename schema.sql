-- Script de criação do banco de dados para Encurtou.pro
-- Crie o banco de dados antes: CREATE DATABASE encurtador_db;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(10) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subdomain` varchar(50) DEFAULT 'ROOT',
  `path` varchar(100) NOT NULL,
  `original_url` text NOT NULL,
  `title` VARCHAR(255) DEFAULT 'Página sem título',
  `url_ios` VARCHAR(2048) DEFAULT NULL,
  `url_android` VARCHAR(2048) DEFAULT NULL,
  `url_windows` VARCHAR(2048) DEFAULT NULL,
  `type` ENUM('link', 'qr') DEFAULT 'link',
  `destination_type` VARCHAR(30) DEFAULT NULL,
  `destination_metadata` JSON DEFAULT NULL,
  `is_ab_test` tinyint(1) DEFAULT 0,
  `tracking_enabled` tinyint(1) DEFAULT 0,
  `clicks` INT DEFAULT 0,
  `start_date` DATETIME NULL,
  `end_date` DATETIME NULL,
  `expire_clicks` INT NULL,
  `fallback_url` VARCHAR(2048) NULL,
  `trigger_clicks` INT NULL,
  `trigger_url` VARCHAR(2048) NULL,
  `status` enum('active','paused') DEFAULT 'active',
  `creator_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_link` (`subdomain`,`path`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `links_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `link_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `url` text NOT NULL,
  `weight` int(11) NOT NULL DEFAULT 50,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  CONSTRAINT `link_variants_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clicks_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `click_uid` varchar(64) UNIQUE NULL,
  `link_id` int(11) NOT NULL,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `visitor_country` varchar(50) DEFAULT 'Desconhecido',
  `visitor_region` varchar(100) DEFAULT 'Desconhecido',
  `visitor_city` varchar(100) DEFAULT 'Desconhecido',
  `user_agent` text DEFAULT NULL,
  `referer` text DEFAULT NULL,
  `source` enum('digital','qr') DEFAULT 'digital',
  `clicked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `variant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  KEY `variant_id` (`variant_id`),
  KEY `idx_clicks_clicked_at` (`clicked_at`),
  KEY `idx_clicks_link_clicked` (`link_id`, `clicked_at`),
  KEY `idx_clicks_link_source_clicked` (`link_id`, `source`, `clicked_at`),
  CONSTRAINT `clicks_log_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clicks_log_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `link_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `conversion_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `ref_slug` varchar(150) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_action_link` (`link_id`, `ref_slug`),
  KEY `link_id` (`link_id`),
  CONSTRAINT `conv_actions_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `conversions_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `click_id` int(11) NOT NULL,
  `action_id` int(11) NOT NULL,
  `metadata` json DEFAULT NULL,
  `converted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `click_id` (`click_id`),
  KEY `action_id` (`action_id`),
  KEY `idx_converted_at` (`converted_at`),
  CONSTRAINT `conv_log_ibfk_1` FOREIGN KEY (`click_id`) REFERENCES `clicks_log` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conv_log_ibfk_2` FOREIGN KEY (`action_id`) REFERENCES `conversion_actions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `analytics_daily_agg` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `agg_date` date NOT NULL,
  `link_id` int(11) DEFAULT NULL,
  `utm_campaign` varchar(150) DEFAULT NULL,
  `source` enum('digital','qr') DEFAULT 'digital',
  `clicks_total` int unsigned NOT NULL DEFAULT 0,
  `conversions_total` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_daily_user_bucket` (`user_id`, `agg_date`, `link_id`, `utm_campaign`, `source`),
  KEY `idx_daily_user_date` (`user_id`, `agg_date`),
  KEY `idx_daily_campaign_date` (`utm_campaign`, `agg_date`),
  CONSTRAINT `analytics_daily_agg_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `analytics_daily_agg_link_fk` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `bio_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `avatar_url` text DEFAULT NULL,
  `theme_bg_color` varchar(20) DEFAULT '#0f172a',
  `theme_btn_color` varchar(20) DEFAULT '#2BF6D1',
  `theme_btn_style` enum('solid','outline','rounded','pill') DEFAULT 'solid',
  `theme_text_color` varchar(20) DEFAULT '#ffffff',
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `pix_key` varchar(150) DEFAULT NULL,
  `template_id` int(11) DEFAULT 1,
  `page_type` varchar(30) DEFAULT 'bio',
  `page_status` varchar(20) DEFAULT 'published',
  `selected_goal_key` varchar(80) DEFAULT NULL,
  `builder_schema_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bio_pages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bio_links` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bio_form_submissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bio_page_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitter_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bio_page_created` (`bio_page_id`,`created_at`),
  CONSTRAINT `bio_form_submissions_page_fk` FOREIGN KEY (`bio_page_id`) REFERENCES `bio_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bio_form_answers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` bigint(20) UNSIGNED NOT NULL,
  `block_id` varchar(80) NOT NULL,
  `field_id` varchar(80) NOT NULL,
  `field_key` varchar(180) NOT NULL,
  `field_type` varchar(32) NOT NULL,
  `value_text` longtext DEFAULT NULL,
  `value_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_submission` (`submission_id`),
  KEY `idx_field_key_lookup` (`field_key`),
  CONSTRAINT `bio_form_answers_sub_fk` FOREIGN KEY (`submission_id`) REFERENCES `bio_form_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
