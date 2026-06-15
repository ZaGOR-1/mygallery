-- Idempotent migration: add private/hidden album flag.
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND COLUMN_NAME = 'is_private'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `albums` ADD COLUMN `is_private` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT ''albums.is_private already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND INDEX_NAME = 'idx_albums_is_private'
);
SET @sql := IF(
  @index_exists = 0,
  'ALTER TABLE `albums` ADD KEY `idx_albums_is_private` (`is_private`)',
  'SELECT ''idx_albums_is_private already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
