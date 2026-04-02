CREATE DATABASE IF NOT EXISTS swiftly_db;
USE swiftly_db;

CREATE TABLE IF NOT EXISTS `links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `long_url` TEXT NOT NULL,
    `short_code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(255) DEFAULT 'Untitled Link',
    `summary` TEXT,
    `category` VARCHAR(50),
    `accent_color` VARCHAR(10),
    `clicks` INT DEFAULT 0,
    `user_ip` VARCHAR(45),
    `creator_id` VARCHAR(64),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL
);

CREATE TABLE IF NOT EXISTS `analytics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `link_id` INT NOT NULL,
    `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rate_limits (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- ---------------------------------------------------------
-- DISTRIBUTED INFRASTRUCTURE (B5: SHARDING)
-- ---------------------------------------------------------

-- Create Node-Alpha (Shard 1)
-- Handles short_codes starting with 0-9 and a-m
CREATE TABLE IF NOT EXISTS links_shard_1 LIKE links;

-- Create Node-Beta (Shard 2)
-- Handles short_codes starting with n-z and A-Z
CREATE TABLE IF NOT EXISTS links_shard_2 LIKE links;


-- ---------------------------------------------------------
-- ABUSE PREVENTION (B4: RATE LIMITING)
-- ---------------------------------------------------------

-- Tracks request frequency per IP to prevent spamming
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    request_count INT DEFAULT 1,
    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(ip_address)
) ENGINE=InnoDB;