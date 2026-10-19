-- Atualização de Estrutura: Gestão de Equipes
-- 1. Tabela de limites na tabela de planos existent
ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `max_team_members` INT(11) NOT NULL DEFAULT 1;

-- Atualizar limites dos planos existentes
UPDATE `plans` SET `max_team_members` = 1 WHERE `id` IN (1, 2);
UPDATE `plans` SET `max_team_members` = 2 WHERE `id` = 3;
UPDATE `plans` SET `max_team_members` = 10 WHERE `id` = 4;

-- 2. Tabela de Equipes (Dono do Plano)
CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `owner_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT 'Minha Equipe',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_owner` (`owner_id`),
  CONSTRAINT `fk_teams_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Membros
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `team_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `role` ENUM('admin', 'member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_team` (`user_id`),
  CONSTRAINT `fk_team_members_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
