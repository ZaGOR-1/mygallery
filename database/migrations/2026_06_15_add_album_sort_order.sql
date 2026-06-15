-- Idempotent migration: add custom album ordering.
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `albums` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0',
  'SELECT ''albums.sort_order already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND INDEX_NAME = 'idx_albums_sort_order'
);
SET @sql := IF(
  @index_exists = 0,
  'ALTER TABLE `albums` ADD KEY `idx_albums_sort_order` (`sort_order`)',
  'SELECT ''idx_albums_sort_order already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
