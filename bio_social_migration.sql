-- Migration to support Social Buttons and Goal Templates in Bio Pages
ALTER TABLE `bio_links` 
ADD COLUMN `link_type` ENUM('link', 'social', 'whatsapp', 'pix') DEFAULT 'link' AFTER `url`,
ADD COLUMN `social_network` VARCHAR(50) DEFAULT NULL AFTER `link_type`,
ADD COLUMN `social_value` TEXT DEFAULT NULL AFTER `social_network`;

-- Note: whatsapp and pix in bio_pages already exist, but here we allow them as separate blocks in bio_links for better flexibility.
