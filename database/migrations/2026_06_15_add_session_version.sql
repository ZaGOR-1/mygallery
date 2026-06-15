ALTER TABLE `admins` ADD COLUMN `session_version` INT NOT NULL DEFAULT 1 AFTER `password_hash`;
