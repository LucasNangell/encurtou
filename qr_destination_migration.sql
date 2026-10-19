-- Migração: destinos de QR Codes dinâmicos 100% editáveis
-- Adiciona colunas `destination_type` e `destination_metadata` na tabela links
-- Executar manualmente ou via run_qr_destination_migration.php

ALTER TABLE `links`
    ADD COLUMN `destination_type` VARCHAR(30) DEFAULT NULL AFTER `type`,
    ADD COLUMN `destination_metadata` JSON DEFAULT NULL AFTER `destination_type`;

-- Backfill: marca os QR Codes antigos como tipo "legacy" para a UI poder tratar
UPDATE `links`
SET `destination_type` = 'legacy'
WHERE `type` = 'qr'
  AND `destination_type` IS NULL;
