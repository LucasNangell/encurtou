-- Bio Page formulário: submissões e respostas
-- Execute no banco (Hostinger/phpMyAdmin) ou via run_bio_form_tables.php

CREATE TABLE IF NOT EXISTS `bio_form_submissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bio_page_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitter_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bio_page_created` (`bio_page_id`,`created_at`),
  CONSTRAINT `bio_form_submissions_page_fk` FOREIGN KEY (`bio_page_id`) REFERENCES `bio_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bio_form_answers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` bigint(20) UNSIGNED NOT NULL,
  `block_id` varchar(80) NOT NULL,
  `field_id` varchar(80) NOT NULL,
  `field_key` varchar(180) NOT NULL,
  `field_type` varchar(32) NOT NULL,
  `value_text` longtext DEFAULT NULL,
  `value_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_submission` (`submission_id`),
  KEY `idx_field_key_lookup` (`field_key`),
  CONSTRAINT `bio_form_answers_sub_fk` FOREIGN KEY (`submission_id`) REFERENCES `bio_form_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
