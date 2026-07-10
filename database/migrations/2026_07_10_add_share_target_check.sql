-- Idempotent defense-in-depth: every share link targets exactly one photo or album.
SET @constraint_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'share_links'
    AND CONSTRAINT_NAME = 'chk_share_links_exactly_one_target'
    AND CONSTRAINT_TYPE = 'CHECK'
);
SET @sql := IF(
  @constraint_exists = 0,
  'ALTER TABLE `share_links` ADD CONSTRAINT `chk_share_links_exactly_one_target` CHECK ((`photo_id` IS NOT NULL AND `album_id` IS NULL) OR (`photo_id` IS NULL AND `album_id` IS NOT NULL))',
  'SELECT ''share_links target CHECK already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
