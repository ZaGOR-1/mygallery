<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

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
