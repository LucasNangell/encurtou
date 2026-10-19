-- Migração de Planos e Subdomínios (V3)

-- Adicionar novas colunas à tabela de planos
ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `analytics_days` INT(11) NOT NULL DEFAULT 0;
ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `max_subdomains` INT(11) NOT NULL DEFAULT 0;

-- Criar tabela de subdomínios vinculados ao usuário
CREATE TABLE IF NOT EXISTS `user_subdomains` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `subdomain` VARCHAR(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_subdomain` (`user_id`, `subdomain`),
  KEY `subdomain` (`subdomain`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Atualizar limites e features dos planos
-- Start (Free): 0 analytics, 0 subdomains, clicks "unlimited" (set to huge), no tracking, no redirect
UPDATE `plans` SET 
    `max_links` = 50, 
    `max_qrs` = 5, 
    `max_bio_pages` = 1, 
    `max_clicks_monthly` = 999999999, 
    `analytics_days` = 0, 
    `max_subdomains` = 0,
    `features_json` = '{"custom_domain": false, "deep_linking": false, "tracking": false, "redirect": false, "advanced_geo": false}'
WHERE `id` = 1;

-- Creator: 30 days analytics, 1 subdomain, unlimited clicks, no tracking, redirect enabled
UPDATE `plans` SET 
    `max_links` = 1000, 
    `max_qrs` = 50, 
    `max_bio_pages` = 5, 
    `max_clicks_monthly` = 999999999, 
    `analytics_days` = 30, 
    `max_subdomains` = 1,
    `features_json` = '{"custom_domain": true, "deep_linking": false, "tracking": false, "redirect": true, "advanced_geo": true}'
WHERE `id` = 2;

-- Pro: 365 days analytics, 5 subdomains, unlimited clicks, tracking enabled, redirect enabled
UPDATE `plans` SET 
    `max_links` = 10000, 
    `max_qrs` = 500, 
    `max_bio_pages` = 25, 
    `max_clicks_monthly` = 999999999, 
    `analytics_days` = 365, 
    `max_subdomains` = 5,
    `features_json` = '{"custom_domain": true, "deep_linking": true, "tracking": true, "redirect": true, "advanced_geo": true, "bulk_utm": true}'
WHERE `id` = 3;

-- Business Founder: 730 days analytics, 50 subdomains, unlimited clicks, tracking enabled, redirect enabled
UPDATE `plans` SET 
    `max_links` = 999999, 
    `max_qrs` = 999999, 
    `max_bio_pages` = 100, 
    `max_clicks_monthly` = 999999999, 
    `analytics_days` = 730, 
    `max_subdomains` = 50,
    `features_json` = '{"custom_domain": true, "deep_linking": true, "tracking": true, "redirect": true, "advanced_geo": true, "bulk_utm": true}'
WHERE `id` = 4;
