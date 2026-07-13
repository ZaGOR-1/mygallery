<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

assert_true(is_file($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'media.php'), 'media.php controller must exist');

$largeHtaccess = file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'large' . DIRECTORY_SEPARATOR . '.htaccess');
$thumbHtaccess = file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . '.htaccess');
assert_true(str_contains((string) $largeHtaccess, 'Require all denied'), 'public/uploads/large must deny direct web access');
assert_true(str_contains((string) $thumbHtaccess, 'Require all denied'), 'public/uploads/thumbnails must deny direct web access');

$fileFunctions = file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'file_functions.php');
assert_true(str_contains((string) $fileFunctions, "url_with_query('media.php'"), 'image helpers must generate media.php URLs');

$mediaController = file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'media.php');
$mediaAccessFunctions = file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'media_access_functions.php');
assert_true(str_contains((string) $mediaController, 'album_is_private'), 'media.php must check album privacy');
assert_true(str_contains((string) $mediaController, 'is_admin_logged_in()'), 'media.php must allow private media for logged-in admin only');
assert_true(str_contains((string) $mediaController, 'media_share_token_allows_photo'), 'media.php must validate share tokens for private media');
assert_true(str_contains((string) $mediaController, 'send_private_cache_headers();'), 'media responses must not remain public-cacheable across a privacy toggle');
assert_true(str_contains((string) $mediaAccessFunctions, 'find_share_link_by_token($token)'), 'media access helper must use the shared token lookup');
assert_false(str_contains((string) $mediaController, 'function media_share_token_allows_photo'), 'media controller must not redeclare extracted access helpers');

$gallery = file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'gallery.php');
$index = file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php');
$adminIndex = file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'index.php');
assert_false(str_contains((string) $gallery, "uploads_url('thumbnails'"), 'public gallery must not render direct thumbnail URLs');
assert_false(str_contains((string) $index, "uploads_url('thumbnails'"), 'homepage must not render direct thumbnail URLs');
assert_false(str_contains((string) $adminIndex, "uploads_url('thumbnails'"), 'admin index must not render direct thumbnail URLs');
