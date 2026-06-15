-- Idempotent migration: store SHA-256 hash for duplicate original detection.
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND COLUMN_NAME = 'original_sha256'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `photos` ADD COLUMN `original_sha256` CHAR(64) NULL AFTER `exif_json`',
  'SELECT ''photos.original_sha256 already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND INDEX_NAME = 'idx_photos_original_sha256_unique'
);
SET @sql := IF(
  @index_exists = 0,
  'ALTER TABLE `photos` ADD UNIQUE KEY `idx_photos_original_sha256_unique` (`original_sha256`)',
  'SELECT ''idx_photos_original_sha256_unique already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
