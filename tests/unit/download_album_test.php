<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

// Verify ZipArchive extension is installed
assert_true(class_exists('ZipArchive'), 'ZipArchive extension must be enabled in PHP');

assert_equals('Test Album', safe_zip_filename('Test Album!'), 'Should strip exclamation mark');
assert_equals('Альбом 123', safe_zip_filename('Альбом 123?'), 'Should support Cyrillic characters');
assert_equals('album-name_1.2', safe_zip_filename('album-name_1.2'), 'Should keep dashes, underscores and dots');
assert_equals('album', safe_zip_filename('$%#@'), 'Should fallback to album if all characters are unsafe');

assert_equals('file.jpg', safe_zip_entry_filename('..\\..\\file.jpg', 5), 'ZIP entry must strip Windows traversal segments');
assert_equals('file.jpg', safe_zip_entry_filename('C:\\fakepath\\file.jpg', 5), 'ZIP entry must strip Windows fakepath');
assert_equals('photo_7.jpg', safe_zip_entry_filename("..\x00\n.jpg", 7), 'dot/control-only ZIP name must use ID fallback');
assert_equals('Фото літо.jpg', safe_zip_entry_filename('/tmp/Фото літо.jpeg', 8), 'ZIP entry must preserve safe Unicode stem');
assert_equals('_CON.jpg', safe_zip_entry_filename('CON.jpg', 9), 'ZIP entry must avoid Windows reserved names');
$longZipName = safe_zip_entry_filename(str_repeat('я', 300) . '.jpg', 10);
assert_true(strlen($longZipName) <= 184, 'ZIP entry must have a cross-platform byte limit');
foreach (['/', '\\', "\0", "\n", "\r"] as $unsafeCharacter) {
    assert_false(str_contains($longZipName, $unsafeCharacter), 'ZIP entry must be a single safe segment');
}
$usedZipNames = [];
assert_equals('Photo.jpg', reserve_unique_zip_entry_filename('Photo.jpg', $usedZipNames), 'first ZIP name should be unchanged');
assert_equals('photo_1.jpg', reserve_unique_zip_entry_filename('photo.jpg', $usedZipNames), 'case-insensitive duplicate ZIP name should get a suffix');

$sensitiveHeaders = album_zip_response_cache_headers(true, 1000);
assert_true(in_array('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', $sensitiveHeaders, true), 'sensitive ZIP must be private/no-store');
assert_true(in_array('Pragma: no-cache', $sensitiveHeaders, true), 'sensitive ZIP must disable legacy caches');
$publicHeaders = album_zip_response_cache_headers(false, 1000);
assert_true(in_array('Cache-Control: public, max-age=300', $publicHeaders, true), 'public ZIP may use a short explicit cache');

$photoRows = [
    [
        'id' => 1,
        'filename' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg',
        'original_name' => 'one.jpg',
        'original_sha256' => str_repeat('a', 64),
        'file_size' => 123,
        'created_at' => '2026-01-01 10:00:00',
        'updated_at' => '2026-01-01 10:00:00',
    ],
];

$baseFingerprint = album_zip_cache_fingerprint(5, 'Album', 'opt', 'public', $photoRows);
$photoRows[0]['file_size'] = 124;
assert_false($baseFingerprint === album_zip_cache_fingerprint(5, 'Album', 'opt', 'public', $photoRows), 'ZIP cache fingerprint must change when file_size changes');

$photoRows[0]['file_size'] = 123;
$photoRows[0]['original_name'] = 'renamed.jpg';
assert_false($baseFingerprint === album_zip_cache_fingerprint(5, 'Album', 'opt', 'public', $photoRows), 'ZIP cache fingerprint must change when original_name changes');

$photoRows[0]['original_name'] = 'one.jpg';
assert_false($baseFingerprint === album_zip_cache_fingerprint(5, 'Album', 'orig', 'admin', $photoRows), 'ZIP cache fingerprint must change when variant/scope changes');

$downloadAlbumController = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'download_album.php');
$albumZipHelpers = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'album_zip_functions.php');
assert_true(str_contains($downloadAlbumController, 'if (!@rename($tempZipFile, $cacheFile))'), 'download_album.php must handle ZIP cache rename failure');
assert_true(str_contains($downloadAlbumController, '$downloadFile = $tempZipFile'), 'download_album.php must stream the verified temp ZIP when cache rename fails');
assert_true(str_contains($albumZipHelpers, 'unlink_file_with_log($path'), 'album ZIP helper must clean up temp ZIP after fallback streaming');
assert_true(str_contains($downloadAlbumController, 'acquire_album_zip_generation_lock($cacheKey)'), 'download_album.php must lock ZIP generation per cache key');
assert_true(
    str_contains($albumZipHelpers, "zip_'") && str_contains($albumZipHelpers, "hash('sha256', \$cacheKey)") && str_contains($albumZipHelpers, "'.lock'"),
    'album ZIP helper must derive generation lock names from the cache key'
);
assert_true(str_contains($downloadAlbumController, 'release_album_zip_generation_lock($generationLock)'), 'download_album.php must release ZIP generation locks before streaming');
assert_true(str_contains($albumZipHelpers, 'send_security_headers();'), 'album ZIP streaming must send security headers');
assert_true(str_contains($downloadAlbumController, 'safe_zip_entry_filename'), 'album ZIP entries must use cross-platform sanitization');
assert_false(str_contains($downloadAlbumController, 'function album_download_client_ip'), 'download controller must not redeclare extracted ZIP helpers');
