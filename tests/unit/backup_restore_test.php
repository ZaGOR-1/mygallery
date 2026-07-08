<?php

declare(strict_types=1);

$backupScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'backup.php');
assert_true(str_contains($backupScript, "'share_links'"), 'backup.php must include share_links table in export list');
assert_true(str_contains($backupScript, "'schema_migrations'"), 'backup.php must include schema_migrations so restore does not re-run migrations');

$schemaSql = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
assert_true(str_contains($schemaSql, 'CREATE TABLE IF NOT EXISTS `schema_migrations`'), 'schema.sql must create schema_migrations for clean installs');

$restoreScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'restore.php');
assert_true(str_contains($restoreScript, "=== '..'"), 'restore.php must prevent Zip Slip by checking for ..');
assert_true(str_contains($restoreScript, "str_starts_with(\$normalized, '/')"), 'restore.php must prevent absolute paths');

$healthScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'health.php');
assert_true(str_contains($healthScript, "'share_links'"), 'health.php must include share_links table in DB check');
assert_true(str_contains($healthScript, "'schema_migrations'"), 'health.php must include schema_migrations table in DB check');
assert_true(str_contains($healthScript, 'Album cover consistency'), 'health.php must report album cover consistency');

$selfCheckScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'self_check.php');
assert_true(str_contains($selfCheckScript, "'schema_migrations'"), 'self_check.php must include schema_migrations table in DB check');
assert_true(str_contains($selfCheckScript, 'invalid_album_cover_count'), 'self_check.php must check album cover consistency');

$verifyScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'verify_backup.php');
assert_true(str_contains($verifyScript, '$hasMismatch = true'), 'verify_backup.php must fail on manifest/media count mismatches');
assert_true(str_contains($verifyScript, 'exit(1);'), 'verify_backup.php must return non-zero on invalid backups');
