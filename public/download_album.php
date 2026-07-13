<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

if (!(bool) (app_config()['ALBUM_ZIP_ENABLED'] ?? true)) {
    app_http_error('Завантаження альбомів ZIP тимчасово вимкнено.', 503);
}

if (!class_exists('ZipArchive')) {
    app_http_error('Клас ZipArchive не встановлений у PHP.', 500);
}

$albumId = null;
$albumName = '';
$token = request_raw_string($_GET, 'token', '', 64);
$albumIdParam = request_int($_GET, 'album_id', null, 1);

if ($token !== '') {
    if (!valid_share_token($token)) {
        app_http_error('Посилання не знайдено.', 404);
    }

    $share = find_share_link_by_token($token);
    if (!$share || share_link_is_expired($share) || empty($share['album_id'])) {
        app_http_error('Посилання не знайдено.', 404);
    }

    $albumId = (int) $share['album_id'];
    $stmt = db()->prepare('SELECT name FROM albums WHERE id = ?');
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();
    if (!$album) {
        app_http_error('Альбом не знайдено.', 404);
    }
    $albumName = (string) $album['name'];
} elseif ($albumIdParam !== null) {
    $albumId = $albumIdParam;
    $stmt = db()->prepare('SELECT name, is_private FROM albums WHERE id = ?');
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();

    // Missing and private albums intentionally look identical to anonymous users.
    if (!$album || ((int) $album['is_private'] === 1 && !is_admin_logged_in())) {
        app_http_error('Альбом не знайдено.', 404);
    }
    $albumName = (string) $album['name'];
} else {
    app_http_error('Вкажіть album_id або token.', 400);
}

$zipCacheDir = trash_path() . DIRECTORY_SEPARATOR . 'zip_cache';
if (!ensure_private_directory($zipCacheDir)) {
    app_http_error('Папка ZIP cache недоступна для запису.', 500);
}

enforce_album_download_cooldown($albumId, $token);

$stmt = db()->prepare('
    SELECT photos.id, photos.filename, photos.original_name, photos.original_sha256,
           photos.file_size, photos.created_at, photos.updated_at
    FROM photos
    WHERE photos.album_id = ?
    ORDER BY photos.created_at ASC, photos.id ASC
');
$stmt->execute([$albumId]);
$photos = $stmt->fetchAll();

if (!$photos) {
    app_http_error('Альбом порожній.', 400);
}

$maximumPhotos = max(1, min(1000, (int) (app_config()['ALBUM_ZIP_MAX_PHOTOS'] ?? 200)));
if (count($photos) > $maximumPhotos) {
    app_http_error('Альбом занадто великий для ZIP (максимум ' . $maximumPhotos . ' фотографій).', 413);
}

$canDownloadOriginals = is_admin_logged_in();
$sensitiveDownload = $token !== '' || $canDownloadOriginals;
$variant = $canDownloadOriginals ? 'orig' : 'opt';
$cacheScope = $canDownloadOriginals ? 'admin-originals' : 'optimized';
$archiveName = safe_zip_filename($albumName);
$totalSize = 0;
$validFiles = [];
$missingPhotoIds = [];
$addedNames = [];

// Cache lookup uses immutable filename + size + mtime metadata only. Expensive
// SHA-256 reads are delayed until a cache miss and protected by the global lock.
foreach ($photos as $photo) {
    $filePath = $canDownloadOriginals
        ? safe_existing_storage_file_path('originals', (string) $photo['filename'])
        : safe_existing_upload_file_path('large', (string) $photo['filename']);

    if ($filePath === null || !is_file($filePath) || is_link($filePath)) {
        $missingPhotoIds[] = (int) $photo['id'];
        continue;
    }

    $sourceSize = filesize($filePath);
    $sourceMtime = filemtime($filePath);
    if (!is_int($sourceSize) || !is_int($sourceMtime)) {
        app_http_error('Не вдалося перевірити файл фотографії #' . (int) $photo['id'] . '.', 500);
    }

    $safeName = safe_zip_entry_filename(
        (string) ($photo['original_name'] ?: $photo['filename']),
        (int) $photo['id']
    );
    $entryName = reserve_unique_zip_entry_filename($safeName, $addedNames);
    $totalSize += $sourceSize;
    $validFiles[] = array_merge($photo, [
        'path' => $filePath,
        'entry_name' => $entryName,
        'source_kind' => $canDownloadOriginals ? 'original' : 'optimized-large',
        'source_size' => $sourceSize,
        'source_mtime' => $sourceMtime,
        'source_sha256' => '',
    ]);
}

if ($missingPhotoIds !== []) {
    $label = $canDownloadOriginals ? 'оригінали' : 'optimized large-файли';
    app_http_error('ZIP не створено: відсутні ' . $label . ' для фото #' . implode(', #', $missingPhotoIds) . '.', 409);
}

$maximumBytes = max(1, (int) (app_config()['ALBUM_ZIP_MAX_SOURCE_BYTES'] ?? 500 * 1024 * 1024));
if ($totalSize > $maximumBytes) {
    app_http_error('Альбом завеликий для ZIP.', 413);
}

$cacheFingerprint = album_zip_cache_fingerprint($albumId, $albumName, $variant, $cacheScope, $validFiles);
$cacheKey = 'album_' . $albumId . '_' . $variant . '_' . substr($cacheFingerprint, 0, 32) . '.zip';
$cacheFile = $zipCacheDir . DIRECTORY_SEPARATOR . $cacheKey;
$lightExpectedEntries = array_map(
    static fn (array $file): array => [
        'entry_name' => (string) $file['entry_name'],
        'source_size' => (int) $file['source_size'],
    ],
    $validFiles
);

if (album_zip_cache_is_valid($cacheFile, $cacheFingerprint, $lightExpectedEntries)) {
    @touch($cacheFile);
    @touch(album_zip_cache_metadata_path($cacheFile));
    stream_album_zip_file($cacheFile, $archiveName, false, $sensitiveDownload);
}
if (is_file($cacheFile) || is_file(album_zip_cache_metadata_path($cacheFile))) {
    delete_album_zip_cache_pair($cacheFile, 'Invalid album ZIP cache cleanup');
}

$generationLock = acquire_album_zip_generation_lock($cacheKey);
$globalGenerationLock = null;
$tempZipFile = null;

try {
    // Another request may have populated the cache while this one waited.
    if (album_zip_cache_is_valid($cacheFile, $cacheFingerprint, $lightExpectedEntries)) {
        release_album_zip_generation_lock($generationLock);
        @touch($cacheFile);
        @touch(album_zip_cache_metadata_path($cacheFile));
        stream_album_zip_file($cacheFile, $archiveName, false, $sensitiveDownload);
    }

    $globalGenerationLock = acquire_album_zip_global_lock();
    $maximumCacheBytes = max(1, (int) (app_config()['ALBUM_ZIP_CACHE_MAX_BYTES'] ?? 2 * 1024 * 1024 * 1024));
    $maximumSeconds = max(10, (int) (app_config()['ALBUM_ZIP_MAX_GENERATION_SECONDS'] ?? 120));
    $startedAt = microtime(true);
    $deadline = $startedAt + $maximumSeconds;

    $tempZipFile = tempnam(trash_path(), 'album_zip_');
    if ($tempZipFile === false) {
        throw new RuntimeException('Не вдалося створити тимчасовий ZIP-файл.');
    }
    enforce_private_file_permissions($tempZipFile);

    $fullExpectedEntries = write_album_zip_file($tempZipFile, $validFiles, $deadline);
    if (!verify_album_zip_file($tempZipFile, $fullExpectedEntries, $deadline)) {
        throw new RuntimeException('Готовий ZIP не пройшов count/size/SHA-256 перевірку.');
    }
    assert_album_zip_deadline($deadline);
    $generatedZipSize = filesize($tempZipFile);
    if (!is_int($generatedZipSize) || $generatedZipSize < 1 || $generatedZipSize > $maximumCacheBytes) {
        throw new RuntimeException('Готовий ZIP перевищує дозволену квоту кешу.');
    }
    enforce_album_zip_cache_quota($zipCacheDir, $maximumCacheBytes, $generatedZipSize);

    if (!rename($tempZipFile, $cacheFile)) {
        $downloadFile = $tempZipFile;
        $tempZipFile = null;
        release_album_zip_generation_lock($globalGenerationLock);
        $globalGenerationLock = null;
        release_album_zip_generation_lock($generationLock);
        $generationLock = null;
        stream_album_zip_file($downloadFile, $archiveName, true, $sensitiveDownload);
    }
    $tempZipFile = null;
    enforce_private_file_permissions($cacheFile);

    if (!write_album_zip_cache_metadata($cacheFile, $cacheFingerprint, count($lightExpectedEntries))) {
        // The archive remains safe to serve once, but is not trusted as a cache.
        release_album_zip_generation_lock($globalGenerationLock);
        $globalGenerationLock = null;
        release_album_zip_generation_lock($generationLock);
        $generationLock = null;
        stream_album_zip_file($cacheFile, $archiveName, true, $sensitiveDownload);
    }

    enforce_album_zip_cache_quota($zipCacheDir, $maximumCacheBytes, 0, $cacheFile);
} catch (Throwable $exception) {
    if (is_string($tempZipFile) && is_file($tempZipFile)) {
        unlink_file_with_log($tempZipFile, 'Failed album ZIP cleanup');
    }
    if (is_file($cacheFile) && !is_file(album_zip_cache_metadata_path($cacheFile))) {
        unlink_file_with_log($cacheFile, 'Incomplete album ZIP cache cleanup');
    }
    release_album_zip_generation_lock($globalGenerationLock);
    release_album_zip_generation_lock($generationLock);
    app_http_error('Не вдалося створити повний ZIP-архів.', 500, $exception);
}

release_album_zip_generation_lock($globalGenerationLock);
release_album_zip_generation_lock($generationLock);
stream_album_zip_file($cacheFile, $archiveName, false, $sensitiveDownload);
