<?php

declare(strict_types=1);

$backupScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'backup.php');
assert_true(str_contains($backupScript, "'share_links'"), 'backup.php must include share_links table in export list');

$restoreScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'restore.php');
assert_true(str_contains($restoreScript, "=== '..'"), 'restore.php must prevent Zip Slip by checking for ..');
assert_true(str_contains($restoreScript, "str_starts_with(\$normalized, '/')"), 'restore.php must prevent absolute paths');

$healthScript = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'health.php');
assert_true(str_contains($healthScript, "'share_links'"), 'health.php must include share_links table in DB check');
