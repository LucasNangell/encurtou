ALTER TABLE `links` 
ADD COLUMN `start_date` DATETIME NULL AFTER `clicks`,
ADD COLUMN `end_date` DATETIME NULL AFTER `start_date`,
ADD COLUMN `expire_clicks` INT NULL AFTER `end_date`,
ADD COLUMN `fallback_url` VARCHAR(2048) NULL AFTER `expire_clicks`,
ADD COLUMN `trigger_clicks` INT NULL AFTER `fallback_url`,
ADD COLUMN `trigger_url` VARCHAR(2048) NULL AFTER `trigger_clicks`;
