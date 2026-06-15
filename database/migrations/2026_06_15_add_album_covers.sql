-- Idempotent migration: add optional album cover photo support.
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND COLUMN_NAME = 'cover_photo_id'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `albums` ADD COLUMN `cover_photo_id` INT UNSIGNED NULL AFTER `name`',
  'SELECT ''albums.cover_photo_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND INDEX_NAME = 'idx_albums_cover_photo_id'
);
SET @sql := IF(
  @index_exists = 0,
  'ALTER TABLE `albums` ADD KEY `idx_albums_cover_photo_id` (`cover_photo_id`)',
  'SELECT ''idx_albums_cover_photo_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
