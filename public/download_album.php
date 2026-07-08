<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

if (!class_exists('ZipArchive')) {
    app_http_error('Клас ZipArchive не встановлений у PHP.', 500);
}

function album_download_client_ip(): string
{
    return client_ip(default: 'cli');
}

function album_download_lock_key(int $albumId, string $token): string
{
    // Ключ лише за IP + scope: User-Agent підробляється тривіально, тож включення його
    // дозволяло б анонімному клієнту щоразу отримувати свіжий cooldown-bucket і обходити
    // захист від вичерпання ресурсів (ZIP до 200 фото / 500 МБ).
    $scope = $token !== '' ? 'share:' . hash('sha256', $token) : 'album:' . $albumId;

    return hash('sha256', album_download_client_ip() . '|' . $scope);
}

function cleanup_album_download_locks(string $dir, int $olderThanSeconds = 86400): void
{
    if (random_int(1, 100) !== 1) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.lock') ?: [] as $file) {
        if (is_file($file) && (time() - (int) filemtime($file)) > $olderThanSeconds) {
            @unlink($file);
        }
    }
}

function enforce_album_download_cooldown(int $albumId, string $token): void
{
    $dir = storage_path('download_locks');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        app_http_error('Не вдалося створити папку для download rate limit.', 500);
    }

    if (!is_writable($dir)) {
        app_http_error('Папка download rate limit недоступна для запису.', 500);
    }

    cleanup_album_download_locks($dir);

    $cooldownSeconds = is_admin_logged_in() ? 15 : 90;
    $lockPath = $dir . DIRECTORY_SEPARATOR . album_download_lock_key($albumId, $token) . '.lock';
    $handle = fopen($lockPath, 'c+');
    if ($handle === false) {
        app_http_error('Не вдалося перевірити download rate limit.', 500);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            app_http_error('Не вдалося заблокувати download rate limit.', 500);
        }

        $lastDownload = (int) trim((string) stream_get_contents($handle));
        $now = time();
        $remaining = $cooldownSeconds - ($now - $lastDownload);

        if ($lastDownload > 0 && $remaining > 0) {
            flock($handle, LOCK_UN);
            fclose($handle);
            if (!headers_sent()) {
                header('Retry-After: ' . $remaining);
            }
            app_http_error('Зачекайте ' . $remaining . ' сек. перед повторним завантаженням ZIP.', 429);
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) $now);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
    }
}

function album_zip_generation_lock_path(string $cacheKey): string
{
    return storage_path('download_locks') . DIRECTORY_SEPARATOR . 'zip_' . hash('sha256', $cacheKey) . '.lock';
}

function acquire_album_zip_generation_lock(string $cacheKey)
{
    $dir = storage_path('download_locks');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        app_http_error('Не вдалося створити папку для ZIP lock.', 500);
    }

    if (!is_writable($dir)) {
        app_http_error('Папка ZIP lock недоступна для запису.', 500);
    }

    $handle = fopen(album_zip_generation_lock_path($cacheKey), 'c+');
    if ($handle === false) {
        app_http_error('Не вдалося створити ZIP lock.', 500);
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        app_http_error('Не вдалося заблокувати генерацію ZIP.', 500);
    }

    return $handle;
}

function release_album_zip_generation_lock(mixed $handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function stream_album_zip_file(string $path, string $archiveName, bool $deleteAfterStream = false): never
{
    $downloadSize = filesize($path);
    if ($downloadSize === false) {
        if ($deleteAfterStream) {
            unlink_file_with_log($path, 'Album ZIP temp cleanup');
        }

        app_http_error('Не вдалося прочитати ZIP-архів.', 500);
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . rawurlencode($archiveName) . '.zip"');
    header('Content-Length: ' . $downloadSize);
    header('Pragma: public');
    header('Cache-Control: max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

    readfile($path);
    if ($deleteAfterStream) {
        unlink_file_with_log($path, 'Album ZIP temp cleanup');
    }
    exit;
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
    $stmt = db()->prepare('SELECT * FROM share_links WHERE token = ?');
    $stmt->execute([$token]);
    $share = $stmt->fetch();

    if (!$share) {
        app_http_error('Посилання не знайдено.', 404);
    }

    if (!empty($share['expires_at']) && strtotime($share['expires_at']) < time()) {
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
$variant = $canDownloadOriginals ? 'orig' : 'opt';
$cacheScope = $token !== '' ? 'share:' . hash('sha256', $token) : ($canDownloadOriginals ? 'admin' : 'public');
$cacheFingerprint = album_zip_cache_fingerprint($albumId, $albumName, $variant, $cacheScope, $photos);

$cacheKey = 'album_' . $albumId . '_' . $variant . '_' . substr($cacheFingerprint, 0, 32) . '.zip';
$cacheFile = $zipCacheDir . DIRECTORY_SEPARATOR . $cacheKey;

// Helper to construct Ukrainian/Cyrillic safe zip file name
function safe_zip_filename(string $albumName): string
{
    $safe = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $albumName);
    $safe = trim($safe);
    return $safe === '' ? 'album' : $safe;
}

$archiveName = safe_zip_filename($albumName);

if (is_file($cacheFile)) {
    stream_album_zip_file($cacheFile, $archiveName);
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
    stream_album_zip_file($cacheFile, $archiveName);
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
    $origName = basename($file['original_name']);
    $base = pathinfo($origName, PATHINFO_FILENAME);
    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    
    $finalName = $origName;
    $counter = 1;
    while (in_array(strtolower($finalName), $addedNames, true)) {
        $finalName = $base . '_' . $counter . ($ext !== '' ? '.' . $ext : '');
        $counter++;
    }
    $addedNames[] = strtolower($finalName);
    
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
stream_album_zip_file($downloadFile, $archiveName, $deleteDownloadFileAfterStream);
