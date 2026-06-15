<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

require_admin();

$id = get_int('id');
if ($id === null || $id < 1) {
    app_http_error('Фотографію не знайдено.', 404);
}

try {
    $stmt = db()->prepare('SELECT id, filename, original_name, mime_type, file_size FROM photos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $photo = $stmt->fetch();
} catch (Throwable $exception) {
    app_http_error('Не вдалося отримати фотографію.', 500, $exception);
}

if (!$photo) {
    app_http_error('Фотографію не знайдено.', 404);
}

$filename = (string) $photo['filename'];
$path = safe_existing_storage_file_path('originals', $filename)
    ?? safe_existing_upload_file_path('originals', $filename);

if ($path === null || !is_file($path) || !is_readable($path)) {
    app_http_error('Оригінальний файл не знайдено.', 404);
}

$downloadName = safe_original_name((string) ($photo['original_name'] ?: $filename));
if ($downloadName === '') {
    $downloadName = $filename;
}

$asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: $filename;
$encodedName = rawurlencode($downloadName);
$size = filesize($path);

while (ob_get_level() > 0) {
    ob_end_clean();
}

send_security_headers();
header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="' . addcslashes($asciiName, '"\\') . '"; filename*=UTF-8\'\'' . $encodedName);
header('Content-Transfer-Encoding: binary');
header('X-Content-Type-Options: nosniff');
if ($size !== false) {
    header('Content-Length: ' . $size);
}

$handle = fopen($path, 'rb');
if ($handle === false) {
    app_http_error('Не вдалося відкрити оригінальний файл.', 500);
}

fpassthru($handle);
fclose($handle);
exit;
