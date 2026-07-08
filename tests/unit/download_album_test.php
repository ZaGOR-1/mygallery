<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

// Verify ZipArchive extension is installed
assert_true(class_exists('ZipArchive'), 'ZipArchive extension must be enabled in PHP');

// Test zip filename sanitization logic
function test_safe_zip_filename(string $albumName): string
{
    $safe = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $albumName);
    $safe = trim($safe);
    return $safe === '' ? 'album' : $safe;
}

assert_equals('Test Album', test_safe_zip_filename('Test Album!'), 'Should strip exclamation mark');
assert_equals('Альбом 123', test_safe_zip_filename('Альбом 123?'), 'Should support Cyrillic characters');
assert_equals('album-name_1.2', test_safe_zip_filename('album-name_1.2'), 'Should keep dashes, underscores and dots');
assert_equals('album', test_safe_zip_filename('$%#@'), 'Should fallback to album if all characters are unsafe');

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
assert_true(str_contains($downloadAlbumController, 'if (!@rename($tempZipFile, $cacheFile))'), 'download_album.php must handle ZIP cache rename failure');
assert_true(str_contains($downloadAlbumController, '$downloadFile = $tempZipFile'), 'download_album.php must stream the verified temp ZIP when cache rename fails');
assert_true(str_contains($downloadAlbumController, 'unlink_file_with_log($path'), 'download_album.php must clean up temp ZIP after fallback streaming');
assert_true(str_contains($downloadAlbumController, 'acquire_album_zip_generation_lock($cacheKey)'), 'download_album.php must lock ZIP generation per cache key');
assert_true(
    str_contains($downloadAlbumController, "zip_'") && str_contains($downloadAlbumController, "hash('sha256', \$cacheKey)") && str_contains($downloadAlbumController, "'.lock'"),
    'download_album.php must derive generation lock names from the cache key'
);
assert_true(str_contains($downloadAlbumController, 'release_album_zip_generation_lock($generationLock)'), 'download_album.php must release ZIP generation locks before streaming');
