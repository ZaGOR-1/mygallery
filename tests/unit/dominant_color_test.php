<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

// 1. Check if column exists in the database
$stmt = db()->query("SHOW COLUMNS FROM photos");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
assert_true(in_array('dominant_color', $columns, true), 'photos table must contain dominant_color column');

// 2. Test get_image_dominant_color with a dynamic red image
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
