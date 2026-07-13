<?php

declare(strict_types=1);

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_derivative_' . bin2hex(random_bytes(6));
assert_true(mkdir($directory, 0700), 'derivative test directory should be created');
$source = $directory . DIRECTORY_SEPARATOR . 'source.jpg';
$destination = $directory . DIRECTORY_SEPARATOR . 'source.webp';
$image = imagecreatetruecolor(2, 2);
assert_true($image instanceof GdImage, 'derivative fixture image should be created');

try {
    assert_true(imagejpeg($image, $source, 90), 'derivative JPEG fixture should be written');
    assert_equals(5, file_put_contents($destination, 'stale'), 'stale derivative fixture should be written');
    $oldWasVisibleDuringEncoding = false;
    $published = create_atomic_image_derivative(
        $source,
        'webp',
        90,
        static function (GdImage $decoded, string $temporary, int $quality) use ($destination, &$oldWasVisibleDuringEncoding): bool {
            $oldWasVisibleDuringEncoding = file_get_contents($destination) === 'stale';
            return imagejpeg($decoded, $temporary, $quality);
        },
        IMAGETYPE_JPEG
    );
    assert_true($published, 'valid derivative should be published');
    assert_true($oldWasVisibleDuringEncoding, 'old derivative must remain available until atomic publication');
    assert_false(file_get_contents($destination) === 'stale', 'published derivative must replace stale bytes');
    assert_equals(IMAGETYPE_JPEG, (int) (getimagesize($destination)[2] ?? 0), 'published derivative must pass format validation');
    assert_equals([], glob($directory . DIRECTORY_SEPARATOR . '.derivative_*') ?: [], 'temporary derivative files must be cleaned');

    assert_equals(5, file_put_contents($destination, 'stale'), 'failed-generation stale fixture should be written');
    $published = create_atomic_image_derivative(
        $source,
        'webp',
        90,
        static fn (GdImage $decoded, string $temporary, int $quality): bool => false,
        IMAGETYPE_JPEG
    );
    assert_false($published, 'failed derivative encoder must be reported');
    assert_false(is_file($destination), 'failed generation must remove a stale derivative');
    assert_equals([], glob($directory . DIRECTORY_SEPARATOR . '.derivative_*') ?: [], 'failed generation must clean its temporary file');
} finally {
    imagedestroy($image);
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}
