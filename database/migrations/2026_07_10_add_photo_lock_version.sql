-- Idempotent migration: use an integer revision for precise optimistic locking.
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND COLUMN_NAME = 'lock_version'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `photos` ADD COLUMN `lock_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `dominant_color`',
  'SELECT ''photos.lock_version already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
