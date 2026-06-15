ALTER TABLE `photos`
ADD COLUMN `original_sha256` CHAR(64) NULL AFTER `exif_json`,
ADD UNIQUE KEY `idx_photos_original_sha256_unique` (`original_sha256`);
