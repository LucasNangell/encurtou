-- Script de Migração: Administrativo e Contas Especiais (V3)
-- Executar este arquivo usando o mysql no console

-- 1. Alterar a tabela users para permitir flag administrativa
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `is_admin` TINYINT(1) DEFAULT 0;

-- 2. Tabela para Gerenciamento de Contas Especiais (Exceções e bypasses)
CREATE TABLE IF NOT EXISTS `user_account_overrides` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `special_account_type` VARCHAR(50) DEFAULT 'interna', -- Ex: interna, vip, parceiro
  `billing_bypass` TINYINT(1) DEFAULT 0, -- Ignora cobranças
  `custom_plan_id` INT(11) DEFAULT NULL, -- Força os limites de um plano, independentemente da assinatura
  `custom_limits_json` TEXT DEFAULT NULL, -- Overrides de limites pontuais (links, qrs, abas bio)
  `custom_features_json` TEXT DEFAULT NULL, -- Overrides de feature flags Booleanos pontuais
  `granted_by` INT(11) DEFAULT NULL, -- Usuário admin que concedeu (para auditoria futura)
  `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_override` (`user_id`),
  CONSTRAINT `fk_user_overrides` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Configurações de Sistema Global
CREATE TABLE IF NOT EXISTS `system_config` (
  `config_key` VARCHAR(50) NOT NULL,
  `config_value` TEXT DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Inserir configurações essenciais por default
INSERT IGNORE INTO `system_config` (`config_key`, `config_value`, `description`) VALUES
('founder_edition_active', '1', 'Habilita a venda da Founder Edition Business'),
('founder_max_seats', '500', 'Número mÃ¡ximo de vagas disponíveis para a Founder Edition'),
('grace_period_days', '3', 'Dias de tolerÃ¢ncia (grace period) após vencimento'),
('maintenance_mode', '0', 'Se ativado bloqueia acesso público para manutenção');

-- 5. Promover o email nangellbusiness@gmail.com a Admin Master!
-- Verifica se ele existe, se já existe, faz UPDATE, senão INSERT
INSERT IGNORE INTO `users` (`name`, `nickname`, `email`, `phone`, `password`, `is_admin`) 
VALUES ('Administrador Master', 'Nangell Admin', 'nangellbusiness@gmail.com', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE `is_admin` = 1;
