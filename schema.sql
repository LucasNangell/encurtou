-- Script de criação do banco de dados para Encurtou.pro
-- Crie o banco de dados antes: CREATE DATABASE encurtador_db;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(10) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subdomain` varchar(50) DEFAULT 'ROOT',
  `path` varchar(100) NOT NULL,
  `original_url` text NOT NULL,
  `title` VARCHAR(255) DEFAULT 'Página sem título',
  `url_ios` VARCHAR(2048) DEFAULT NULL,
  `url_android` VARCHAR(2048) DEFAULT NULL,
  `url_windows` VARCHAR(2048) DEFAULT NULL,
  `type` ENUM('link', 'qr') DEFAULT 'link',
  `clicks` INT DEFAULT 0,
  `status` enum('active','paused') DEFAULT 'active',
  `creator_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_link` (`subdomain`,`path`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `links_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clicks_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `visitor_country` varchar(50) DEFAULT 'Desconhecido',
  `user_agent` text DEFAULT NULL,
  `referer` text DEFAULT NULL,
  `source` enum('digital','qr') DEFAULT 'digital',
  `clicked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  CONSTRAINT `clicks_log_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
