<?php

declare(strict_types=1);

// Test directory functions
$rootPath = project_root_path();
assert_true(is_dir($rootPath), 'Project root path should be a valid directory');
assert_true(is_dir(public_path()), 'Public path should be a valid directory');
assert_true(is_dir(storage_path()), 'Storage path should be a valid directory');

// Test safe paths
// They return null on invalid paths instead of throwing
assert_equals(null, safe_upload_file_path('large', '../file.jpg'), 'Directory traversal should return null for upload paths');
assert_equals(null, safe_storage_file_path('originals', '../file.jpg'), 'Directory traversal should return null for storage paths');
assert_equals(null, safe_trash_file_path('../file.jpg'), 'Directory traversal should return null for trash paths');

$safePathRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_path_test_' . bin2hex(random_bytes(6));
$safePathChild = $safePathRoot . DIRECTORY_SEPARATOR . 'child';
$externalSentinel = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_sentinel_' . bin2hex(random_bytes(6));
assert_true(mkdir($safePathRoot, 0700), 'path test root should be created');
assert_true(mkdir($safePathChild, 0700), 'path test child should be created');
assert_true(file_put_contents($safePathChild . DIRECTORY_SEPARATOR . 'normal.txt', 'safe') !== false, 'normal path fixture');
assert_true(file_put_contents($externalSentinel, 'sentinel') !== false, 'external sentinel fixture');
try {
    assert_true(
        filesystem_path_is_safe_child($safePathChild . DIRECTORY_SEPARATOR . 'normal.txt', $safePathRoot),
        'normal nested file must pass containment validation'
    );
    $linkPath = $safePathChild . DIRECTORY_SEPARATOR . 'external-link';
    if (@symlink($externalSentinel, $linkPath)) {
        assert_false(filesystem_path_is_safe_child($linkPath, $safePathRoot), 'symlink to external file must be rejected');
        unlink($linkPath);
        assert_true(is_file($externalSentinel), 'external sentinel must survive symlink validation');
    }
} finally {
    unlink($safePathChild . DIRECTORY_SEPARATOR . 'normal.txt');
    rmdir($safePathChild);
    rmdir($safePathRoot);
    unlink($externalSentinel);
}

assert_equals(null, safe_upload_file_path('large', '/etc/passwd'), 'Absolute paths should return null');
assert_equals(null, safe_upload_file_path('large', 'C:\\Windows\\System32\\cmd.exe'), 'Windows absolute paths should return null');

// Normal valid path should not throw
ensure_upload_folders();
$validName = random_photo_name();
$safePath = safe_upload_file_path('large', $validName);
assert_true($safePath !== null, 'safe_upload_file_path should not return null for valid paths');
assert_true(str_starts_with($safePath, public_path()), 'Safe upload path should be inside public path');

$configuredAppUrl = rtrim((string) app_config()['APP_URL'], '/');
assert_equals($configuredAppUrl . '/share.php?token=test', absolute_url('share.php?token=test'), 'copied share links must include the configured APP_URL origin');
