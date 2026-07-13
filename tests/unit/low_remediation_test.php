<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$functions = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php');
$fileFunctions = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'file_functions.php');
$migration = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'migrate_legacy_originals.php');
$runtimeCleanup = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'cleanup_runtime.php');
$trashRecovery = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'recover_trash.php');
$shareAdmin = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'share.php');
$readme = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'README.md');
$nginx = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'ci' . DIRECTORY_SEPARATOR . 'nginx.conf');

assert_true(str_contains($functions, 'with_runtime_log_lock('), 'log rotation and append must share a process lock');
assert_true(str_contains($functions, 'append_rotating_private_log('), 'private logs must use one checked append-and-rotation helper');
assert_true(str_contains($shareAdmin, 'append_rotating_private_log($logFile, $message, 1048576, 5)'), 'share audit must use checked locked rotation');
assert_true(str_contains($shareAdmin, "app_log('Share audit fallback:"), 'share audit write failure must have an observable fallback');
assert_false(str_contains($shareAdmin, 'private_file_put_contents($logFile, $message'), 'share audit controller must not bypass the checked log helper');
assert_true(substr_count($fileFunctions, 'enforce_shared_file_permissions(') >= 3, 'JPEG and atomic WebP/AVIF derivative writes must enforce shared 0640 permissions');
assert_true(str_contains($migration, 'acquire_media_maintenance_lock(LOCK_EX)'), 'legacy original migration must exclude concurrent media mutation');
assert_true(str_contains($migration, 'enforce_private_file_permissions($target)'), 'migrated originals must enforce private 0600 permissions');
assert_true(str_contains($migration, '$failed++;') && str_contains($migration, '!@unlink($legacyPath)'), 'legacy migration must fail when requested deletion fails');
assert_true(str_contains($trashRecovery, 'exit($totalErrors > 0 ? 1 : 0);'), 'trash recovery must report manifest and I/O errors through its exit status');
assert_true(str_contains($runtimeCleanup, '$operationErrors++') && str_contains($runtimeCleanup, '$unsafeEntries > 0 || $operationErrors > 0'), 'runtime cleanup must report requested I/O failures through its exit status');
assert_true(str_contains($runtimeCleanup, '$busySkipped++'), 'runtime cleanup must distinguish lock contention from failed operations');
assert_false(str_contains($readme, 'try_files $uri $uri/ /index.php'), 'Nginx production sample must not soft-route unknown URLs to the homepage');
assert_true(str_contains($readme, 'return 404;') && str_contains($readme, 'download_album|404|500'), 'Nginx production sample must allowlist routes and return real 404 responses');
assert_true(str_contains($nginx, 'return 404;') && str_contains($nginx, 'download_album|404|500'), 'Nginx CI must exercise the same explicit route policy');
assert_false(str_contains($readme, 'location ^~ /assets/'), 'Nginx documentation must let the dotfile deny regex inspect asset paths');
assert_false(str_contains($nginx, 'location ^~ /assets/'), 'Nginx CI config must let the dotfile deny regex inspect asset paths');

$logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_log_rotation_' . bin2hex(random_bytes(6));
assert_true(mkdir($logDirectory, 0700), 'log rotation fixture directory should be created');
$logFile = $logDirectory . DIRECTORY_SEPARATOR . 'app.log';
try {
    assert_equals(32, file_put_contents($logFile, str_repeat('x', 32)), 'log rotation fixture should be written');
    assert_true(append_rotating_private_log($logFile, "audit line\n", 8, 2), 'locked log rotation and exact append should succeed');
    assert_equals(str_repeat('x', 32), file_get_contents($logFile . '.1'), 'oversized log must rotate without losing its segment');
    assert_equals("audit line\n", file_get_contents($logFile), 'new audit line must be appended after rotation');
    assert_true(is_file($logFile . '.rotation.lock'), 'rotation must use a persistent sibling lock file');
    $blockedLog = $logDirectory . DIRECTORY_SEPARATOR . 'blocked.log';
    assert_true(mkdir($blockedLog, 0700), 'blocked log fixture should be created');
    assert_false(append_rotating_private_log($blockedLog, "lost line\n", 8, 2), 'log helper must report an append failure');
    rmdir($blockedLog);
} finally {
    foreach (glob($logDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($logDirectory);
}
