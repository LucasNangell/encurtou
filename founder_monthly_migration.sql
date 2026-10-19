-- Migração: Founder Edition mensal com preço congelado

-- Preços congelados por membro Founder
ALTER TABLE `founder_allocations`
  ADD COLUMN IF NOT EXISTS `locked_monthly_price` DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `locked_annual_price` DECIMAL(10,2) DEFAULT NULL;

-- Plano 5: Founder Edition (limites equivalentes ao Business, preços editáveis pelo admin)
INSERT INTO `plans` (
  `id`, `name`, `tier_level`,
  `monthly_price`, `annual_price`,
  `max_links`, `max_qrs`, `max_bio_pages`, `max_campaigns`, `max_clicks_monthly`,
  `analytics_days`, `max_subdomains`,
  `features_json`, `is_active`
) VALUES (
  5, 'Founder Edition', 999,
  149.90, 1439.00,
  999999, 999999, 100, 999999, 999999999,
  730, 50,
  '{"custom_domain": true, "deep_linking": true, "tracking": true, "redirect": true, "advanced_geo": true, "bulk_utm": true}',
  1
) ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `tier_level` = VALUES(`tier_level`),
  `max_links` = VALUES(`max_links`),
  `max_qrs` = VALUES(`max_qrs`),
  `max_bio_pages` = VALUES(`max_bio_pages`),
  `max_campaigns` = VALUES(`max_campaigns`),
  `max_clicks_monthly` = VALUES(`max_clicks_monthly`),
  `analytics_days` = VALUES(`analytics_days`),
  `max_subdomains` = VALUES(`max_subdomains`),
  `features_json` = VALUES(`features_json`),
  `is_active` = VALUES(`is_active`);
