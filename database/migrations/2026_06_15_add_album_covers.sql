ALTER TABLE `albums` ADD COLUMN `cover_photo_id` INT UNSIGNED NULL AFTER `name`;
ALTER TABLE `albums` ADD CONSTRAINT `fk_albums_cover_photo_id` FOREIGN KEY (`cover_photo_id`) REFERENCES `photos` (`id`) ON DELETE SET NULL;
