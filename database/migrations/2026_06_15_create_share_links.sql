-- Idempotent migration: public share links for photos and albums.
CREATE TABLE IF NOT EXISTS `share_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token` VARCHAR(64) NOT NULL,
  `photo_id` INT UNSIGNED NULL,
  `album_id` INT UNSIGNED NULL,
  `expires_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_share_links_token` (`token`),
  KEY `idx_share_links_photo_id` (`photo_id`),
  KEY `idx_share_links_album_id` (`album_id`),
  CONSTRAINT `fk_share_links_photo_id` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_share_links_album_id` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
