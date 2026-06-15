-- Idempotent migration: invalidate old admin sessions after security-sensitive account changes.
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'admins'
    AND COLUMN_NAME = 'session_version'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `admins` ADD COLUMN `session_version` INT NOT NULL DEFAULT 1 AFTER `password_hash`',
  'SELECT ''admins.session_version already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
