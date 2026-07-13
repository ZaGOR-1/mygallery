<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$shareController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'share.php');
$photoController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'photo.php');
$mediaController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'media.php');
$authFunctions = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth_functions.php');

$headers = private_cache_headers();
assert_true(in_array('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', $headers, true), 'private HTML/media must be no-store');
assert_true(str_contains($shareController, "mygallery_referrer_policy'] = 'no-referrer'"), 'share HTML must suppress referrer leakage');
assert_true(str_contains($shareController, 'send_private_cache_headers();'), 'share HTML must be private/no-store');
assert_true(str_contains($photoController, 'if ($isPrivatePhoto)'), 'photo page must detect private HTML');
assert_true(str_contains($photoController, 'send_private_cache_headers();'), 'private photo HTML must be private/no-store');
assert_true(str_contains($mediaController, 'send_private_cache_headers();'), 'all media must require revalidation instead of a 30-day public cache');
assert_false(str_contains($mediaController, 'max-age=2592000'), 'media controller must not retain privacy-toggle-stale caching');
assert_true(str_contains($authFunctions, "['same-origin', 'no-referrer']"), 'security headers must support a no-referrer private-page policy');
