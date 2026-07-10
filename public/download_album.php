<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

if (!class_exists('ZipArchive')) {
    app_http_error('Клас ZipArchive не встановлений у PHP.', 500);
}

$albumId = null;
$albumName = '';

$token = (string) ($_GET['token'] ?? '');
$albumIdParam = $_GET['album_id'] ?? null;

if ($token !== '') {
    if (!valid_share_token($token)) {
        app_http_error('Посилання не знайдено.', 404);
    }

    // Shared view download
    $share = find_share_link_by_token($token);

    if (!$share) {
        app_http_error('Посилання не знайдено.', 404);
    }

    if (share_link_is_expired($share)) {
        app_http_error('Посилання застаріло.', 404);
    }

    if (empty($share['album_id'])) {
        app_http_error('Некоректний тип посилання.', 400);
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
    // Public download (or admin)
    $albumId = filter_var($albumIdParam, FILTER_VALIDATE_INT);

    if ($albumId === false || $albumId <= 0) {
        app_http_error('Некоректний ID альбому.', 400);
    }

    $stmt = db()->prepare('SELECT name, is_private FROM albums WHERE id = ?');
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();

    if (!$album) {
        app_http_error('Альбом не знайдено.', 404);
    }

    if ((int) $album['is_private'] === 1 && !is_admin_logged_in()) {
        app_http_error('Цей альбом приватний.', 403);
    }

    $albumName = (string) $album['name'];
} else {
    app_http_error('Вкажіть album_id або token.', 400);
}

// Ensure cache directory
$zipCacheDir = trash_path() . DIRECTORY_SEPARATOR . 'zip_cache';
if (!is_dir($zipCacheDir)) {
    @mkdir($zipCacheDir, 0755, true);
}

enforce_album_download_cooldown($albumId, $token);

// Fetch photos in album
$stmt = db()->prepare('
    SELECT photos.id, photos.filename, photos.original_name, photos.original_sha256, photos.file_size, photos.created_at, photos.updated_at
    FROM photos
    WHERE photos.album_id = ?
    ORDER BY photos.created_at ASC, photos.id ASC
');
$stmt->execute([$albumId]);
$photos = $stmt->fetchAll();

if (empty($photos)) {
    app_http_error('Альбом порожній.', 400);
}

// Security checks for ZIP size / photo counts
if (count($photos) > 200) {
    app_http_error('Альбом занадто великий для завантаження архівом (максимум 200 фотографій).', 400);
}

// Only admins receive byte-for-byte private originals from storage/originals.
// Public (?album_id=) and share-token downloads get the optimized uploads/large copy only.
// The flag is part of the cache key so an admin ZIP (originals) is never served to a non-admin.
$canDownloadOriginals = is_admin_logged_in();
$sensitiveDownload = $token !== '' || $canDownloadOriginals;
$variant = $canDownloadOriginals ? 'orig' : 'opt';
$cacheScope = $token !== '' ? 'share:' . hash('sha256', $token) : ($canDownloadOriginals ? 'admin' : 'public');
$cacheFingerprint = album_zip_cache_fingerprint($albumId, $albumName, $variant, $cacheScope, $photos);

$cacheKey = 'album_' . $albumId . '_' . $variant . '_' . substr($cacheFingerprint, 0, 32) . '.zip';
$cacheFile = $zipCacheDir . DIRECTORY_SEPARATOR . $cacheKey;

$archiveName = safe_zip_filename($albumName);

if (is_file($cacheFile)) {
    stream_album_zip_file($cacheFile, $archiveName, false, $sensitiveDownload);
}

$totalSize = 0;
$validFiles = [];

foreach ($photos as $photo) {
    $filePath = null;
    if ($canDownloadOriginals) {
        $filePath = safe_existing_storage_file_path('originals', (string) $photo['filename']);
    }
    if ($filePath === null || !is_file($filePath)) {
        $filePath = safe_existing_upload_file_path('large', (string) $photo['filename']);
    }
    if ($filePath !== null && is_file($filePath)) {
        $totalSize += filesize($filePath);
        $validFiles[] = [
            'id' => (int) $photo['id'],
            'path' => $filePath,
            'original_name' => (string) ($photo['original_name'] ?: $photo['filename']),
        ];
    }
}

if (empty($validFiles)) {
    app_http_error('Не знайдено файлів фотографій на диску.', 404);
}

if ($totalSize > 500 * 1024 * 1024) { // 500 MB
    app_http_error('Альбом занадто великий для завантаження архівом (максимум 500 МБ).', 400);
}

$generationLock = acquire_album_zip_generation_lock($cacheKey);

if (is_file($cacheFile)) {
    release_album_zip_generation_lock($generationLock);
    stream_album_zip_file($cacheFile, $archiveName, false, $sensitiveDownload);
}

// Create ZIP archive
$zip = new ZipArchive();
$tempZipFile = tempnam(trash_path(), 'album_zip_');

if ($tempZipFile === false) {
    release_album_zip_generation_lock($generationLock);
    app_http_error('Не вдалося створити тимчасовий файл.', 500);
}

if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tempZipFile);
    release_album_zip_generation_lock($generationLock);
    app_http_error('Не вдалося відкрити ZIP-архів.', 500);
}

$addedNames = [];
foreach ($validFiles as $file) {
    $origName = safe_zip_entry_filename((string) $file['original_name'], (int) $file['id']);
    $finalName = reserve_unique_zip_entry_filename($origName, $addedNames);
    
    $zip->addFile($file['path'], $finalName);
}

$zip->close();

if (!is_file($tempZipFile) || filesize($tempZipFile) === 0) {
    @unlink($tempZipFile);
    release_album_zip_generation_lock($generationLock);
    app_http_error('Не вдалося створити ZIP-архів.', 500);
}

// Save to cache when possible; if the cache write fails, stream the verified temp file.
$downloadFile = $cacheFile;
$deleteDownloadFileAfterStream = false;
if (!@rename($tempZipFile, $cacheFile)) {
    app_log('Album ZIP cache rename failed: ' . basename($tempZipFile) . ' -> ' . basename($cacheFile));
    $downloadFile = $tempZipFile;
    $deleteDownloadFileAfterStream = true;
}

// Cleanup old cache files (randomly)
if (random_int(1, 20) === 1) {
    foreach (glob($zipCacheDir . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $cachedZip) {
        if (is_file($cachedZip) && (time() - filemtime($cachedZip)) > 86400 * 3) {
            @unlink($cachedZip);
        }
    }
}

// Stream the file
release_album_zip_generation_lock($generationLock);
stream_album_zip_file($downloadFile, $archiveName, $deleteDownloadFileAfterStream, $sensitiveDownload);
