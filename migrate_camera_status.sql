CREATE TABLE IF NOT EXISTS `camera_status` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `camera_status` VARCHAR(32) NOT NULL DEFAULT 'offline',
    `last_event` VARCHAR(32) DEFAULT NULL,
    `category` VARCHAR(80) DEFAULT NULL,
    `confidence` DECIMAL(6,5) DEFAULT NULL,
    `detection_at` DATETIME DEFAULT NULL,
    `last_seen` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `camera_status` (`id`, `camera_status`)
VALUES (1, 'offline')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);