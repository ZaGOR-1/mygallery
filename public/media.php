<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    send_security_headers();
    exit;
}

$id = request_int($_GET, 'id', null, 1);
$variant = request_string($_GET, 'variant', 16, 'large');
$format = strtolower(request_string($_GET, 'format', 8, 'jpg'));
$token = request_raw_string($_GET, 'token', '', 64);

if ($id === null || !in_array($variant, ['large', 'thumbnail'], true) || !in_array($format, ['jpg', 'webp', 'avif'], true)) {
    media_not_found();
}

if ($token !== '') {
    if (!valid_share_token($token)) {
        media_not_found();
    }
    enforce_share_request_rate_limit('media', 600, 60);
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
if ($path === null || !is_file($path) || is_link($path)) {
    media_not_found();
}

$mimeTypes = [
    'jpg' => 'image/jpeg',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
];
$fileSize = filesize($path);
$fileMtime = filemtime($path);
if (!is_int($fileSize) || $fileSize < 0 || !is_int($fileMtime)) {
    media_not_found();
}

$etag = '"' . hash('sha256', basename($path) . '|' . $fileSize . '|' . $fileMtime) . '"';
$lastModified = gmdate('D, d M Y H:i:s', $fileMtime) . ' GMT';

send_security_headers();
send_private_cache_headers();
header('Content-Type: ' . $mimeTypes[$format]);
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);
header('Accept-Ranges: bytes');

$ifNoneMatch = is_string($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
$ifModifiedSince = is_string($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
if (($ifNoneMatch !== '' && hash_equals($etag, $ifNoneMatch))
    || ($ifNoneMatch === '' && is_int($ifModifiedSince) && $ifModifiedSince >= $fileMtime)) {
    http_response_code(304);
    exit;
}

$start = 0;
$end = max(0, $fileSize - 1);
$status = 200;
$rangeHeader = is_string($_SERVER['HTTP_RANGE'] ?? null) ? trim($_SERVER['HTTP_RANGE']) : '';
$ifRange = is_string($_SERVER['HTTP_IF_RANGE'] ?? null) ? trim($_SERVER['HTTP_IF_RANGE']) : '';
$canUseRange = $rangeHeader !== '' && ($ifRange === '' || $ifRange === $etag || $ifRange === $lastModified);

if ($canUseRange) {
    if (preg_match('/\Abytes=(\d*)-(\d*)\z/', $rangeHeader, $matches) !== 1 || $fileSize === 0) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    $rawStart = $matches[1];
    $rawEnd = $matches[2];
    if ($rawStart === '' && $rawEnd === '') {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    if ($rawStart === '') {
        $suffixLength = min($fileSize, max(0, (int) $rawEnd));
        if ($suffixLength < 1) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }
        $start = $fileSize - $suffixLength;
    } else {
        $start = (int) $rawStart;
        if ($start >= $fileSize) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }
        if ($rawEnd !== '') {
            $end = min($end, (int) $rawEnd);
        }
        if ($end < $start) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }
    }
    $status = 206;
}

$length = $fileSize === 0 ? 0 : ($end - $start + 1);
http_response_code($status);
if ($status === 206) {
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
}
header('Content-Length: ' . $length);

if ($method === 'HEAD' || $length === 0) {
    exit;
}

$handle = @fopen($path, 'rb');
if ($handle === false || fseek($handle, $start) !== 0) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    media_not_found();
}

$remaining = $length;
try {
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(1024 * 1024, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
} finally {
    fclose($handle);
}

exit;
