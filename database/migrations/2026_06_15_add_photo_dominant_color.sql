-- Idempotent migration: store dominant color placeholders for photos.
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND COLUMN_NAME = 'dominant_color'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `photos` ADD COLUMN `dominant_color` VARCHAR(7) NULL',
  'SELECT ''photos.dominant_color already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
