<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

// Schema coverage does not require a live DB; the image algorithm is a pure unit test.
$schema = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
assert_true(str_contains($schema, '`dominant_color` VARCHAR(7)'), 'photos schema must contain dominant_color column');

// Test get_image_dominant_color with a dynamic red image
$img = imagecreatetruecolor(10, 10);
$red = imagecolorallocate($img, 255, 0, 0);
imagefill($img, 0, 0, $red);
$tmpFile = tempnam(sys_get_temp_dir(), 'test_img_');
imagejpeg($img, $tmpFile);
imagedestroy($img);

try {
    $color = get_image_dominant_color($tmpFile);
    assert_true(str_starts_with($color, '#'), 'Dominant color must start with #');
    assert_true(in_array($color, ['#ff0000', '#fe0000'], true), 'Dominant color of pure red image must be close to #ff0000 (allowing JPEG lossy compression variance)');
} finally {
    if (is_file($tmpFile)) {
        @unlink($tmpFile);
    }
}
