ALTER TABLE `bio_pages`
ADD COLUMN `page_type` VARCHAR(30) DEFAULT 'bio' AFTER `template_id`,
ADD COLUMN `page_status` VARCHAR(20) DEFAULT 'published' AFTER `page_type`,
ADD COLUMN `selected_goal_key` VARCHAR(80) DEFAULT NULL AFTER `page_status`,
ADD COLUMN `builder_schema_json` LONGTEXT DEFAULT NULL AFTER `selected_goal_key`;