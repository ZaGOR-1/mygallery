<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

function media_not_found(): never
{
    http_response_code(404);
    send_security_headers();
    exit;
}

function media_share_token_allows_photo(string $token, array $photo): bool
{
    if (!valid_share_token($token)) {
        return false;
    }

    try {
        $stmt = db()->prepare('SELECT photo_id, album_id, expires_at FROM share_links WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $share = $stmt->fetch();
    } catch (Throwable $exception) {
        app_log_exception($exception, 'Media share token lookup failed');
        return false;
    }

    if (!$share) {
        return false;
    }

    if (!empty($share['expires_at']) && strtotime((string) $share['expires_at']) < time()) {
        return false;
    }

    $photoId = (int) ($photo['id'] ?? 0);
    $albumId = (int) ($photo['album_id'] ?? 0);

    if (!empty($share['photo_id']) && (int) $share['photo_id'] === $photoId) {
        return true;
    }

    return !empty($share['album_id']) && $albumId > 0 && (int) $share['album_id'] === $albumId;
}

function media_file_for_photo(array $photo, string $variant, string $format): ?string
{
    $folder = $variant === 'thumbnail' ? 'thumbnails' : 'large';
    $filename = $variant === 'thumbnail'
        ? (string) ($photo['thumbnail_filename'] ?? '')
        : (string) ($photo['filename'] ?? '');

    if ($filename === '') {
        return null;
    }

    if ($format !== 'jpg') {
        $filename = (string) preg_replace('/\.jpe?g$/i', '.' . $format, $filename);
    }

    return safe_existing_upload_file_path($folder, $filename);
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    send_security_headers();
    exit;
}

$id = get_int('id');
$variant = (string) ($_GET['variant'] ?? 'large');
$format = strtolower((string) ($_GET['format'] ?? 'jpg'));
$token = (string) ($_GET['token'] ?? '');

if ($id === null || $id < 1 || !in_array($variant, ['large', 'thumbnail'], true) || !in_array($format, ['jpg', 'webp', 'avif'], true)) {
    media_not_found();
}

try {
    $photo = fetch_photo_by_id(db(), $id);
} catch (Throwable $exception) {
    app_log_exception($exception, 'Media DB lookup failed');
    media_not_found();
}

if (!$photo) {
    media_not_found();
}

$isPrivate = (int) ($photo['album_is_private'] ?? 0) === 1;
if ($isPrivate && !is_admin_logged_in() && !media_share_token_allows_photo($token, $photo)) {
    media_not_found();
}

$path = media_file_for_photo($photo, $variant, $format);
if ($path === null || !is_file($path)) {
    media_not_found();
}

$mimeTypes = [
    'jpg' => 'image/jpeg',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
];

send_security_headers();
header('Content-Type: ' . $mimeTypes[$format]);
$fileSize = filesize($path);
if ($fileSize !== false) {
    header('Content-Length: ' . (string) $fileSize);
}

if ($isPrivate) {
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
} else {
    header('Cache-Control: public, max-age=2592000');
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 2592000));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    readfile($path);
}

exit;
