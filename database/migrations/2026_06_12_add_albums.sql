CREATE TABLE IF NOT EXISTS `albums` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_albums_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND COLUMN_NAME = 'album_id'
);

SET @sql = IF(
  @column_exists = 0,
  'ALTER TABLE `photos` ADD COLUMN `album_id` INT UNSIGNED NULL AFTER `id`',
  'SELECT "photos.album_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND INDEX_NAME = 'idx_photos_album_id'
);

SET @sql = IF(
  @index_exists = 0,
  'ALTER TABLE `photos` ADD KEY `idx_photos_album_id` (`album_id`)',
  'SELECT "idx_photos_album_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND CONSTRAINT_NAME = 'fk_photos_album_id'
);

SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE `photos` ADD CONSTRAINT `fk_photos_album_id` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE SET NULL',
  'SELECT "fk_photos_album_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
