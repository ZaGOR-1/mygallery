-- Store only SHA-256 hashes of share tokens. The nullable `token` column is
-- retained solely so interrupted upgrades from older releases remain retryable.
SET @token_hash_column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'share_links'
    AND column_name = 'token_hash'
);
SET @token_hash_column_sql := IF(
  @token_hash_column_exists = 0,
  'ALTER TABLE `share_links` ADD COLUMN `token_hash` CHAR(64) NULL AFTER `token`',
  'SELECT 1'
);
PREPARE token_hash_column_stmt FROM @token_hash_column_sql;
EXECUTE token_hash_column_stmt;
DEALLOCATE PREPARE token_hash_column_stmt;

SET @token_hint_column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'share_links'
    AND column_name = 'token_hint'
);
SET @token_hint_column_sql := IF(
  @token_hint_column_exists = 0,
  'ALTER TABLE `share_links` ADD COLUMN `token_hint` CHAR(8) NULL AFTER `token_hash`',
  'SELECT 1'
);
PREPARE token_hint_column_stmt FROM @token_hint_column_sql;
EXECUTE token_hint_column_stmt;
DEALLOCATE PREPARE token_hint_column_stmt;

UPDATE `share_links`
SET `token_hash` = SHA2(`token`, 256),
    `token_hint` = RIGHT(`token`, 8)
WHERE (`token_hash` IS NULL OR `token_hint` IS NULL)
  AND `token` IS NOT NULL
  AND `token` <> '';

-- The NOT NULL conversion below fails closed if a legacy row cannot be hashed.
ALTER TABLE `share_links`
  MODIFY COLUMN `token` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Legacy column; raw tokens are never stored',
  MODIFY COLUMN `token_hash` CHAR(64) NOT NULL,
  MODIFY COLUMN `token_hint` CHAR(8) NOT NULL;

SET @hash_index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'share_links'
    AND index_name = 'idx_share_links_token_hash'
);
SET @hash_index_sql := IF(
  @hash_index_exists = 0,
  'ALTER TABLE `share_links` ADD UNIQUE KEY `idx_share_links_token_hash` (`token_hash`)',
  'SELECT 1'
);
PREPARE hash_index_stmt FROM @hash_index_sql;
EXECUTE hash_index_stmt;
DEALLOCATE PREPARE hash_index_stmt;

SET @hint_index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'share_links'
    AND index_name = 'idx_share_links_token_hint'
);
SET @hint_index_sql := IF(
  @hint_index_exists = 0,
  'ALTER TABLE `share_links` ADD KEY `idx_share_links_token_hint` (`token_hint`)',
  'SELECT 1'
);
PREPARE hint_index_stmt FROM @hint_index_sql;
EXECUTE hint_index_stmt;
DEALLOCATE PREPARE hint_index_stmt;

UPDATE `share_links` SET `token` = NULL WHERE `token` IS NOT NULL;

SET @legacy_token_index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'share_links'
    AND index_name = 'idx_share_links_token'
);
SET @legacy_token_index_sql := IF(
  @legacy_token_index_exists > 0,
  'ALTER TABLE `share_links` DROP INDEX `idx_share_links_token`',
  'SELECT 1'
);
PREPARE legacy_token_index_stmt FROM @legacy_token_index_sql;
EXECUTE legacy_token_index_stmt;
DEALLOCATE PREPARE legacy_token_index_stmt;
