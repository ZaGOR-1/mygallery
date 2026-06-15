<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

// Verify ZipArchive extension is installed
assert_true(class_exists('ZipArchive'), 'ZipArchive extension must be enabled in PHP');

// Test zip filename sanitization logic
function test_safe_zip_filename(string $albumName): string
{
    $safe = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $albumName);
    $safe = trim($safe);
    return $safe === '' ? 'album' : $safe;
}

assert_equals('Test Album', test_safe_zip_filename('Test Album!'), 'Should strip exclamation mark');
assert_equals('Альбом 123', test_safe_zip_filename('Альбом 123?'), 'Should support Cyrillic characters');
assert_equals('album-name_1.2', test_safe_zip_filename('album-name_1.2'), 'Should keep dashes, underscores and dots');
assert_equals('album', test_safe_zip_filename('$%#@'), 'Should fallback to album if all characters are unsafe');
