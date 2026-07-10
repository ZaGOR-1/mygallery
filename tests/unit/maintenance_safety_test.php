<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$backupSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'backup.php');
$cleanupSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'cleanup_runtime.php');
$restoreSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'restore.php');

assert_true(str_contains($backupSource, 'START TRANSACTION WITH CONSISTENT SNAPSHOT'), 'backup must use a consistent DB snapshot');
assert_true(str_contains($backupSource, 'acquire_media_maintenance_lock(LOCK_EX)'), 'backup must exclusively lock media lifecycle changes');
assert_true(str_contains($backupSource, "'photo_inventory' => \$photoInventory"), 'backup manifest must bind media to DB photo inventory');
assert_false(str_contains($cleanupSource, 'getRealPath()'), 'runtime cleanup must not unlink resolved symlink targets');
assert_true(str_contains($cleanupSource, 'filesystem_path_is_safe_child'), 'runtime cleanup must enforce symlink/junction containment');
assert_true(str_contains($restoreSource, 'filesystem_path_is_safe_child'), 'restore must reject symlink/junction targets');

$sharedLock = acquire_media_maintenance_lock(LOCK_SH);
$exclusiveProbe = fopen(media_maintenance_lock_path(), 'c+');
assert_true(is_resource($exclusiveProbe), 'exclusive lock probe should open');
try {
    assert_false(flock($exclusiveProbe, LOCK_EX | LOCK_NB), 'exclusive backup lock must wait while a media mutation lock is held');
} finally {
    fclose($exclusiveProbe);
    release_media_maintenance_lock($sharedLock);
}

$exclusiveLock = acquire_media_maintenance_lock(LOCK_EX);
release_media_maintenance_lock($exclusiveLock);
