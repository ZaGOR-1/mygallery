CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `session_version` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `migration` VARCHAR(255) NOT NULL,
  `run_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `albums` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `cover_photo_id` INT UNSIGNED NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_private` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_albums_name` (`name`),
  KEY `idx_albums_cover_photo_id` (`cover_photo_id`),
  KEY `idx_albums_sort_order` (`sort_order`),
  KEY `idx_albums_is_private` (`is_private`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `photos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `album_id` INT UNSIGNED NULL,
  `filename` VARCHAR(255) NOT NULL,
  `thumbnail_filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `camera_make` VARCHAR(150) NULL,
  `camera_model` VARCHAR(150) NULL,
  `lens_model` VARCHAR(255) NULL,
  `taken_at` DATETIME NULL,
  `exif_json` JSON NULL,
  `original_sha256` CHAR(64) NULL,
  `dominant_color` VARCHAR(7) NULL,
  `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_photos_filename_unique` (`filename`),
  UNIQUE KEY `idx_photos_thumbnail_filename_unique` (`thumbnail_filename`),
  UNIQUE KEY `idx_photos_original_sha256_unique` (`original_sha256`),
  KEY `idx_photos_album_id` (`album_id`),
  KEY `idx_photos_taken_at` (`taken_at`),
  KEY `idx_photos_created_at` (`created_at`),
  KEY `idx_photos_camera_model` (`camera_model`),
  KEY `idx_photos_title` (`title`),
  FULLTEXT KEY `idx_photos_public_search_fulltext` (`title`, `description`),
  FULLTEXT KEY `idx_photos_admin_search_fulltext` (`title`, `description`, `original_name`),
  CONSTRAINT `fk_photos_album_id` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add the album cover foreign key after both albums and photos exist.
SET @constraint_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND CONSTRAINT_NAME = 'fk_albums_cover_photo_id'
);
SET @sql := IF(
  @constraint_exists = 0,
  'ALTER TABLE `albums` ADD CONSTRAINT `fk_albums_cover_photo_id` FOREIGN KEY (`cover_photo_id`) REFERENCES `photos` (`id`) ON DELETE SET NULL',
  'SELECT ''fk_albums_cover_photo_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tags_name` (`name`),
  UNIQUE KEY `idx_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `photo_tags` (
  `photo_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`photo_id`, `tag_id`),
  KEY `idx_photo_tags_tag_id` (`tag_id`),
  CONSTRAINT `fk_photo_tags_photo_id` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_photo_tags_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `last_attempt_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_login_attempts_username_ip` (`username`, `ip_address`),
  KEY `idx_login_attempts_ip_address` (`ip_address`),
  KEY `idx_login_attempts_locked_until` (`locked_until`),
  KEY `idx_login_attempts_last_attempt_at` (`last_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `fk_share_links_album_id` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_share_links_exactly_one_target` CHECK (
    (`photo_id` IS NOT NULL AND `album_id` IS NULL)
    OR (`photo_id` IS NULL AND `album_id` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
