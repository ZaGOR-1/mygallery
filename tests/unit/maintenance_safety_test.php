<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$backupSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'backup.php');
$cleanupSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'cleanup_runtime.php');
$restoreSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'restore.php');
$orphanCleanupSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'cleanup_orphans.php');
$regenerateSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'regenerate_images.php');
$recoverTrashSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'recover_trash.php');

assert_true(str_contains($backupSource, 'START TRANSACTION WITH CONSISTENT SNAPSHOT'), 'backup must use a consistent DB snapshot');
assert_true(str_contains($backupSource, 'acquire_media_maintenance_lock(LOCK_EX)'), 'backup must exclusively lock media lifecycle changes');
assert_true(str_contains($backupSource, "'photo_inventory' => \$photoInventory"), 'backup manifest must bind media to DB photo inventory');
assert_false(str_contains($cleanupSource, 'getRealPath()'), 'runtime cleanup must not unlink resolved symlink targets');
assert_true(str_contains($cleanupSource, 'filesystem_path_is_safe_child'), 'runtime cleanup must enforce symlink/junction containment');
assert_true(str_contains($restoreSource, 'filesystem_path_is_safe_child'), 'restore must reject symlink/junction targets');
assert_true(str_contains($orphanCleanupSource, '$delete ? acquire_media_maintenance_lock(LOCK_EX)'), 'destructive orphan cleanup must exclude web media mutations');
assert_true(str_contains($regenerateSource, '$dryRun ? null : acquire_media_maintenance_lock(LOCK_EX)'), 'image regeneration must exclude web media mutations');
assert_true(str_contains($recoverTrashSource, '$apply ? acquire_media_maintenance_lock(LOCK_EX)'), 'trash recovery apply must exclude web media mutations');

$legacyTestDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_legacy_cleanup_' . bin2hex(random_bytes(6));
assert_true(mkdir($legacyTestDir, 0700), 'legacy cleanup fixture directory should be created');
$legacyOnly = $legacyTestDir . DIRECTORY_SEPARATOR . 'legacy-only.jpg';
$legacyDuplicate = $legacyTestDir . DIRECTORY_SEPARATOR . 'legacy-duplicate.jpg';
$privateDuplicate = $legacyTestDir . DIRECTORY_SEPARATOR . 'private-duplicate.jpg';
$legacyDifferent = $legacyTestDir . DIRECTORY_SEPARATOR . 'legacy-different.jpg';
$privateDifferent = $legacyTestDir . DIRECTORY_SEPARATOR . 'private-different.jpg';
try {
    assert_true(file_put_contents($legacyOnly, 'only-copy') !== false, 'legacy-only fixture should be written');
    assert_true(file_put_contents($legacyDuplicate, 'same-copy') !== false, 'legacy duplicate fixture should be written');
    assert_true(file_put_contents($privateDuplicate, 'same-copy') !== false, 'private duplicate fixture should be written');
    assert_true(file_put_contents($legacyDifferent, 'legacy-copy') !== false, 'different legacy fixture should be written');
    assert_true(file_put_contents($privateDifferent, 'private-copy') !== false, 'different private fixture should be written');

    assert_false(
        legacy_original_cleanup_decision($legacyOnly, null, true)['deletable'],
        'DB-referenced legacy-only original must never be deletable'
    );
    assert_true(
        legacy_original_cleanup_decision($legacyDuplicate, $privateDuplicate, true)['deletable'],
        'equal legacy/private originals may delete only the verified public duplicate'
    );
    assert_false(
        legacy_original_cleanup_decision($legacyDifferent, $privateDifferent, true)['deletable'],
        'different legacy/private originals must require manual review'
    );
    assert_true(
        legacy_original_cleanup_decision($legacyOnly, null, false)['deletable'],
        'unreferenced legacy original remains an orphan cleanup candidate'
    );
} finally {
    foreach (glob($legacyTestDir . DIRECTORY_SEPARATOR . '*') ?: [] as $legacyFixture) {
        unlink($legacyFixture);
    }
    rmdir($legacyTestDir);
}

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
