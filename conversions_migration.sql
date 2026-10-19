-- Migrations para suportar Conversion Tracking avançado

-- Adiciona opções na tabela de Links
ALTER TABLE `links` ADD COLUMN IF NOT EXISTS `tracking_enabled` TINYINT(1) DEFAULT 0 AFTER `is_ab_test`;

-- Adiciona ID único no Click Log
ALTER TABLE `clicks_log` ADD COLUMN IF NOT EXISTS `click_uid` VARCHAR(64) UNIQUE NULL AFTER `id`;

-- Cria a tabela de Ações Registradas pelo Usuário
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

-- Cria a tabela de Logs de Conversão efetivada
CREATE TABLE IF NOT EXISTS `conversions_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `click_id` int(11) NOT NULL,
  `action_id` int(11) NOT NULL,
  `metadata` json DEFAULT NULL,
  `converted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `click_id` (`click_id`),
  KEY `action_id` (`action_id`),
  CONSTRAINT `conv_log_ibfk_1` FOREIGN KEY (`click_id`) REFERENCES `clicks_log` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conv_log_ibfk_2` FOREIGN KEY (`action_id`) REFERENCES `conversion_actions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
