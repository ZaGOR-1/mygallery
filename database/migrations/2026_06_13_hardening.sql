USE `my_photo_gallery`;

SET @index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND INDEX_NAME = 'idx_photos_filename_unique'
);

SET @sql = IF(
  @index_exists = 0,
  'ALTER TABLE `photos` ADD UNIQUE KEY `idx_photos_filename_unique` (`filename`)',
  'SELECT "idx_photos_filename_unique already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'photos'
    AND INDEX_NAME = 'idx_photos_thumbnail_filename_unique'
);

SET @sql = IF(
  @index_exists = 0,
  'ALTER TABLE `photos` ADD UNIQUE KEY `idx_photos_thumbnail_filename_unique` (`thumbnail_filename`)',
  'SELECT "idx_photos_thumbnail_filename_unique already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'login_attempts'
    AND INDEX_NAME = 'idx_login_attempts_ip_address'
);

SET @sql = IF(
  @index_exists = 0,
  'ALTER TABLE `login_attempts` ADD KEY `idx_login_attempts_ip_address` (`ip_address`)',
  'SELECT "idx_login_attempts_ip_address already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'login_attempts'
    AND INDEX_NAME = 'idx_login_attempts_last_attempt_at'
);

SET @sql = IF(
  @index_exists = 0,
  'ALTER TABLE `login_attempts` ADD KEY `idx_login_attempts_last_attempt_at` (`last_attempt_at`)',
  'SELECT "idx_login_attempts_last_attempt_at already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
