<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$phpFiles = array_merge(
    glob($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '*.php') ?: [],
    glob($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . '*.php') ?: []
);

foreach ($phpFiles as $file) {
    $contents = (string) file_get_contents($file);
    assert_false(str_contains($contents, 'onerror='), basename($file) . ' must not contain inline onerror handlers');
    assert_false(str_contains($contents, 'onclick='), basename($file) . ' must not contain inline onclick handlers');
}

$css = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css');
assert_false(str_contains($css, '@import url('), 'CSS must not import external font CSS under self-only CSP');
assert_false(str_contains($css, 'fonts.googleapis.com'), 'CSS must not depend on Google Fonts');
assert_true(str_contains($css, '.filter-panel-inner'), 'Responsive filter rules must include filter-panel-inner');

$mainJs = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'main.js');
assert_true(str_contains($mainJs, 'trapLightboxFocus'), 'main.js must trap focus inside lightbox');
assert_true(str_contains($mainJs, 'previousLightboxFocus'), 'main.js must restore focus after closing lightbox');

$publicHtaccess = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess');
$uploadsHtaccess = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess');
assert_true(str_contains($publicHtaccess, 'webp|avif'), 'public/.htaccess cache rules must include WebP/AVIF');
assert_true(str_contains($uploadsHtaccess, 'webp|avif'), 'public/uploads/.htaccess cache rules must include WebP/AVIF');

$share = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'share.php');
assert_true(str_contains($share, 'valid_share_token($token)'), 'share.php must validate token format before DB lookup');
assert_true(str_contains($share, 'X-Robots-Tag: noindex, noarchive'), 'share.php must send noindex robots header');
assert_true(str_contains($share, 'Share rate limit storage is not writable'), 'share.php must log share rate-limit storage failures');
assert_true(str_contains($share, 'if (is_production())'), 'share.php must fail closed for rate-limit storage failures in production');
assert_true(str_contains($share, 'Служба приватних посилань тимчасово недоступна'), 'share.php must return a temporary-unavailable error when production rate-limit storage is unavailable');

$header = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php');
assert_true(str_contains($header, '<meta name="robots"'), 'header.php must support robots meta tag');

$cleanup = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'cleanup_runtime.php');
assert_true(str_contains($cleanup, 'share_ratelimit'), 'cleanup_runtime.php must clean share_ratelimit');
assert_true(str_contains($cleanup, 'download_locks'), 'cleanup_runtime.php must clean download_locks');

$photoService = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php');
assert_false(str_contains($photoService, "ini_set('memory_limit'"), 'upload flow must not depend on overriding memory_limit at runtime');
