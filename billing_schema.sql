-- Script de Migração: Billing & Assinaturas (V1 e V2)

-- V1 - Tabelas core de Billing
CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `stripe_product_id` VARCHAR(100) DEFAULT NULL,
  `name` VARCHAR(50) NOT NULL,
  `tier_level` INT(11) NOT NULL DEFAULT 0,
  `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `annual_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_links` INT(11) NOT NULL DEFAULT 0,
  `max_qrs` INT(11) NOT NULL DEFAULT 0,
  `max_bio_pages` INT(11) NOT NULL DEFAULT 0,
  `max_campaigns` INT(11) NOT NULL DEFAULT 0,
  `max_clicks_monthly` INT(11) NOT NULL DEFAULT 0,
  `features_json` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `plan_id` INT(11) NOT NULL,
  `stripe_customer_id` VARCHAR(100) DEFAULT NULL,
  `stripe_subscription_id` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active','trialing','past_due','canceled','unpaid') DEFAULT 'active',
  `billing_cycle` ENUM('monthly','annual') DEFAULT 'monthly',
  `current_period_start` DATETIME DEFAULT NULL,
  `current_period_end` DATETIME DEFAULT NULL,
  `cancel_at_period_end` TINYINT(1) DEFAULT 0,
  `is_founder` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stripe_subscription_id` (`stripe_subscription_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` INT(11) DEFAULT NULL,
  `user_id` INT(11) NOT NULL,
  `stripe_invoice_id` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(10,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'BRL',
  `status` ENUM('paid','open','void','uncollectible') DEFAULT 'open',
  `paid_at` DATETIME DEFAULT NULL,
  `hosted_invoice_url` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stripe_invoice_id` (`stripe_invoice_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `billing_events_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(100) NOT NULL,
  `gateway_event_id` VARCHAR(100) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_event_id` (`gateway_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_usage_monthly` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `billing_period` VARCHAR(20) NOT NULL,
  `total_clicks` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_period` (`user_id`, `billing_period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `founder_allocations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` ENUM('pending_payment','active') DEFAULT 'pending_payment',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V2 - Alterações em tabelas core existentes
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `current_plan_id` INT(11) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `billing_status` VARCHAR(20) DEFAULT 'freemium';

ALTER TABLE `links` ADD COLUMN IF NOT EXISTS `is_premium_locked` TINYINT(1) DEFAULT 0;
ALTER TABLE `bio_pages` ADD COLUMN IF NOT EXISTS `is_premium_locked` TINYINT(1) DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_links_premium ON links(is_premium_locked);

-- Inserts dos 4 planos base
-- Se já houver, usaremos IGNORE para evitar falhas em reruns
INSERT IGNORE INTO `plans` (`id`, `name`, `tier_level`, `max_links`, `max_qrs`, `max_bio_pages`, `max_campaigns`, `max_clicks_monthly`, `features_json`) VALUES
(1, 'Start (Free)', 0, 50, 2, 1, 0, 1000, '{"custom_domain": false, "deep_linking": false, "export_svg": false, "logo_qr": false, "advanced_geo": false, "bulk_utm": false}'),
(2, 'Creator', 1, 1000, 50, 5, 5, 50000, '{"custom_domain": true, "max_domains": 1, "deep_linking": false, "export_svg": true, "logo_qr": true, "advanced_geo": true, "bulk_utm": false}'),
(3, 'Pro', 2, 10000, 500, 25, 1000, 500000, '{"custom_domain": true, "max_domains": 5, "deep_linking": true, "export_svg": true, "logo_qr": true, "advanced_geo": true, "bulk_utm": true}'),
(4, 'Business Founder', 999, 999999, 999999, 999999, 999999, 5000000, '{"custom_domain": true, "max_domains": 50, "deep_linking": true, "export_svg": true, "logo_qr": true, "advanced_geo": true, "bulk_utm": true}');
