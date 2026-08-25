-- Migration: create devices and device_messages tables

CREATE TABLE IF NOT EXISTS `devices` (
  `device_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_name` VARCHAR(191) DEFAULT NULL,
  `device_code` VARCHAR(191) NOT NULL,
  `api_key` VARCHAR(191) NOT NULL,
  `bin_id` INT UNSIGNED DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`device_id`),
  UNIQUE KEY `uq_device_code` (`device_code`),
  UNIQUE KEY `uq_api_key` (`api_key`),
  KEY `idx_bin_id` (`bin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `device_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` INT UNSIGNED NOT NULL,
  `payload` JSON NOT NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
