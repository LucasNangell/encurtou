-- Script de migração para adicionar suporte a Testes A/B ao banco de dados existente.

-- 1. Modificar a tabela links
ALTER TABLE `links` ADD COLUMN `is_ab_test` tinyint(1) DEFAULT 0 AFTER `type`;

-- 2. Criar a tabela de variantes do test A/B
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

-- 3. Adicionar rastreamento de variantes na tabela temporária de clicks
ALTER TABLE `clicks_log` ADD COLUMN `variant_id` int(11) DEFAULT NULL AFTER `clicked_at`;
ALTER TABLE `clicks_log` Add INDEX `variant_id` (`variant_id`);
ALTER TABLE `clicks_log` ADD CONSTRAINT `clicks_log_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `link_variants` (`id`) ON DELETE SET NULL;
