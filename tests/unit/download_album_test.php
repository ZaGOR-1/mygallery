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
assert_equals('_CON.txt.jpg', safe_zip_entry_filename('CON.txt.jpg', 9), 'ZIP entry must avoid reserved Windows device names before a dot');
assert_equals('_LPT1.backup.jpg', safe_zip_entry_filename('LPT1.backup.jpeg', 9), 'ZIP entry must avoid numbered Windows device names before a dot');
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

$cooldownStream = fopen('php://temp', 'w+b');
assert_true(is_resource($cooldownStream), 'cooldown state fixture should open');
try {
    assert_equals(0, read_album_download_cooldown_timestamp($cooldownStream), 'empty cooldown state must be a valid first request');
    assert_true(write_album_download_cooldown_timestamp($cooldownStream, 123456), 'cooldown timestamp must use a checked exact write');
    assert_equals(123456, read_album_download_cooldown_timestamp($cooldownStream), 'cooldown timestamp must round-trip');
    assert_true(@ftruncate($cooldownStream, 0) && @rewind($cooldownStream), 'corrupt cooldown fixture should reset');
    assert_equals(7, fwrite($cooldownStream, 'corrupt'), 'corrupt cooldown fixture should be written');
    assert_equals(null, read_album_download_cooldown_timestamp($cooldownStream), 'corrupt cooldown state must fail closed');
} finally {
    fclose($cooldownStream);
}
$readOnlyCooldown = fopen(__FILE__, 'rb');
assert_true(is_resource($readOnlyCooldown), 'read-only cooldown failure fixture should open');
try {
    assert_false(write_album_download_cooldown_timestamp($readOnlyCooldown, 123456), 'cooldown write failure must be observable');
} finally {
    fclose($readOnlyCooldown);
}
$albumZipSource = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'album_zip_functions.php');
assert_true(str_contains($albumZipSource, "app_log('Album ZIP cooldown fail-closed:"), 'cooldown I/O failures must be operationally visible before returning 503');

$photoRows = [
    [
        'id' => 1,
        'filename' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg',
        'original_name' => 'one.jpg',
        'original_sha256' => str_repeat('a', 64),
        'file_size' => 123,
        'created_at' => '2026-01-01 10:00:00',
        'updated_at' => '2026-01-01 10:00:00',
        'entry_name' => 'one.jpg',
        'source_kind' => 'optimized-large',
        'source_size' => 123,
        'source_mtime' => 1000,
        'source_sha256' => str_repeat('b', 64),
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
$sourceFingerprint = album_zip_cache_fingerprint(5, 'Album', 'opt', 'public', $photoRows);
$photoRows[0]['source_mtime']++;
assert_false($sourceFingerprint === album_zip_cache_fingerprint(5, 'Album', 'opt', 'public', $photoRows), 'ZIP cache fingerprint must change with actual source metadata');

$zipTestDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_album_zip_' . bin2hex(random_bytes(6));
assert_true(mkdir($zipTestDir, 0700), 'album ZIP test directory should be created');
$zipSource = $zipTestDir . DIRECTORY_SEPARATOR . 'source.jpg';
$zipPath = $zipTestDir . DIRECTORY_SEPARATOR . 'album.zip';
$zipPayload = 'album-zip-integrity';
try {
    assert_equals(strlen($zipPayload), file_put_contents($zipSource, $zipPayload), 'album ZIP source should be written');
    $expectedEntries = write_album_zip_file($zipPath, [[
        'path' => $zipSource,
        'entry_name' => 'Фото літо.jpg',
        'source_size' => strlen($zipPayload),
    ]], microtime(true) + 5.0);
    assert_equals(hash('sha256', $zipPayload), $expectedEntries[0]['source_sha256'], 'streamed writer must hash source bytes');
    $rawZip = (string) file_get_contents($zipPath);
    $localFlags = unpack('vflags', substr($rawZip, 6, 2));
    $centralOffset = strpos($rawZip, pack('V', 0x02014b50));
    assert_true(is_array($localFlags) && (((int) $localFlags['flags']) & 0x0800) !== 0, 'local ZIP header must declare UTF-8 entry names');
    assert_true(is_int($centralOffset), 'album ZIP must contain a central-directory entry');
    $centralFlags = unpack('vflags', substr($rawZip, (int) $centralOffset + 8, 2));
    assert_true(is_array($centralFlags) && (((int) $centralFlags['flags']) & 0x0800) !== 0, 'central ZIP header must declare UTF-8 entry names');
    assert_true(verify_album_zip_file($zipPath, $expectedEntries), 'album ZIP verifier must accept exact count/size/hash');
    $expectedEntries[0]['source_sha256'] = str_repeat('0', 64);
    assert_false(verify_album_zip_file($zipPath, $expectedEntries), 'album ZIP verifier must reject a payload hash mismatch');
    $expiredZip = $zipTestDir . DIRECTORY_SEPARATOR . 'expired.zip';
    assert_throws(
        static fn (): array => write_album_zip_file($expiredZip, [[
            'path' => $zipSource,
            'entry_name' => 'source.jpg',
            'source_size' => strlen($zipPayload),
        ]], microtime(true) - 1.0),
        RuntimeException::class,
        'streamed album ZIP writer must enforce an expired deadline'
    );
    assert_false(is_file($expiredZip), 'timed-out album ZIP must be removed');
} finally {
    foreach (glob($zipTestDir . DIRECTORY_SEPARATOR . '*') ?: [] as $testFile) {
        unlink($testFile);
    }
    rmdir($zipTestDir);
}

$downloadAlbumController = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'download_album.php');
$albumZipHelpers = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'album_zip_functions.php');
assert_true(str_contains($downloadAlbumController, 'if (!rename($tempZipFile, $cacheFile))'), 'download_album.php must handle ZIP cache rename failure');
assert_true(str_contains($downloadAlbumController, '$downloadFile = $tempZipFile'), 'download_album.php must stream the verified temp ZIP when cache rename fails');
assert_true(str_contains($albumZipHelpers, 'delete_album_zip_cache_pair'), 'album ZIP helper must clean up ZIP and sidecar as one cache pair');
assert_true(str_contains($downloadAlbumController, 'acquire_album_zip_generation_lock($cacheKey)'), 'download_album.php must lock ZIP generation per cache key');
assert_true(
    str_contains($albumZipHelpers, "zip_'") && str_contains($albumZipHelpers, "hash('sha256', \$cacheKey)") && str_contains($albumZipHelpers, "'.lock'"),
    'album ZIP helper must derive generation lock names from the cache key'
);
assert_true(str_contains($downloadAlbumController, 'release_album_zip_generation_lock($generationLock)'), 'download_album.php must release ZIP generation locks before streaming');
assert_true(str_contains($albumZipHelpers, 'send_security_headers();'), 'album ZIP streaming must send security headers');
assert_true(str_contains($downloadAlbumController, 'safe_zip_entry_filename'), 'album ZIP entries must use cross-platform sanitization');
assert_true(str_contains($downloadAlbumController, 'write_album_zip_file($tempZipFile, $validFiles, $deadline)'), 'album ZIP generation must use the chunk-deadline streaming writer');
assert_true(str_contains($downloadAlbumController, 'verify_album_zip_file($tempZipFile, $fullExpectedEntries, $deadline)'), 'album ZIP readback must share the generation deadline');
assert_true(str_contains($downloadAlbumController, '$missingPhotoIds !== []'), 'album ZIP must reject an incomplete source set');
assert_true(str_contains($downloadAlbumController, 'acquire_album_zip_global_lock()'), 'album ZIP generation must have a global concurrency gate');
assert_true(str_contains($albumZipHelpers, 'LOCK_EX | LOCK_NB'), 'album ZIP locks must not block PHP workers');
assert_true(str_contains($albumZipHelpers, 'enforce_album_zip_cache_quota'), 'album ZIP cache must have a disk quota');
assert_false(str_contains($downloadAlbumController, 'function album_download_client_ip'), 'download controller must not redeclare extracted ZIP helpers');
