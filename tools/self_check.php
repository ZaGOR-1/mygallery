<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$sessionPath = storage_path('test_sessions');
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}

session_save_path($sessionPath);
session_id('selfcheck' . bin2hex(random_bytes(4)));

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function check_csrf_cases(): void
{
    start_session();
    $_SESSION = [];
    $_POST = [];

    $_SESSION['csrf_token'] = 'known-token';
    $_POST['csrf_token'] = 'known-token';
    assert_true(verify_csrf() === true, 'CSRF valid token failed.');

    $_POST['csrf_token'] = 'wrong-token';
    assert_true(verify_csrf() === false, 'CSRF wrong token passed.');

    $_POST['csrf_token'] = '';
    assert_true(verify_csrf() === false, 'CSRF empty submitted token passed.');

    unset($_POST['csrf_token']);
    assert_true(verify_csrf() === false, 'CSRF missing submitted token passed.');

    $_POST['csrf_token'] = 'known-token';
    unset($_SESSION['csrf_token']);
    assert_true(verify_csrf() === false, 'CSRF missing session token passed.');
}

function rgb(int $r, int $g, int $b): array
{
    return [$r, $g, $b];
}

function color_at(GdImage $image, int $x, int $y): array
{
    $color = imagecolorat($image, $x, $y);

    return [($color >> 16) & 255, ($color >> 8) & 255, $color & 255];
}

function make_orientation_source(): GdImage
{
    $image = imagecreatetruecolor(3, 2);
    $colors = [
        imagecolorallocate($image, 255, 0, 0),
        imagecolorallocate($image, 0, 255, 0),
        imagecolorallocate($image, 0, 0, 255),
        imagecolorallocate($image, 255, 255, 0),
        imagecolorallocate($image, 255, 0, 255),
        imagecolorallocate($image, 0, 255, 255),
    ];

    $i = 0;
    for ($y = 0; $y < 2; $y++) {
        for ($x = 0; $x < 3; $x++) {
            imagesetpixel($image, $x, $y, $colors[$i]);
            $i++;
        }
    }

    return $image;
}

function expected_orientation_pixels(int $orientation): array
{
    $source = [
        [rgb(255, 0, 0), rgb(0, 255, 0), rgb(0, 0, 255)],
        [rgb(255, 255, 0), rgb(255, 0, 255), rgb(0, 255, 255)],
    ];
    $height = count($source);
    $width = count($source[0]);
    $targetWidth = in_array($orientation, [5, 6, 7, 8], true) ? $height : $width;
    $targetHeight = in_array($orientation, [5, 6, 7, 8], true) ? $width : $height;
    $target = array_fill(0, $targetHeight, array_fill(0, $targetWidth, rgb(0, 0, 0)));

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ($orientation === 1) {
                [$nx, $ny] = [$x, $y];
            } elseif ($orientation === 2) {
                [$nx, $ny] = [$width - 1 - $x, $y];
            } elseif ($orientation === 3) {
                [$nx, $ny] = [$width - 1 - $x, $height - 1 - $y];
            } elseif ($orientation === 4) {
                [$nx, $ny] = [$x, $height - 1 - $y];
            } elseif ($orientation === 5) {
                [$nx, $ny] = [$y, $x];
            } elseif ($orientation === 6) {
                [$nx, $ny] = [$height - 1 - $y, $x];
            } elseif ($orientation === 7) {
                [$nx, $ny] = [$height - 1 - $y, $width - 1 - $x];
            } else {
                [$nx, $ny] = [$y, $width - 1 - $x];
            }

            $target[$ny][$nx] = $source[$y][$x];
        }
    }

    return $target;
}

function check_orientation_cases(): void
{
    for ($orientation = 1; $orientation <= 8; $orientation++) {
        $source = make_orientation_source();
        $result = apply_orientation($source, $orientation);
        $expected = expected_orientation_pixels($orientation);

        assert_true(imagesx($result) === count($expected[0]), 'Orientation ' . $orientation . ' width failed.');
        assert_true(imagesy($result) === count($expected), 'Orientation ' . $orientation . ' height failed.');

        foreach ($expected as $y => $row) {
            foreach ($row as $x => $expectedColor) {
                assert_true(color_at($result, $x, $y) === $expectedColor, 'Orientation ' . $orientation . ' pixel failed.');
            }
        }

        if ($result !== $source) {
            imagedestroy($result);
        }

        imagedestroy($source);
    }
}

check_csrf_cases();
check_orientation_cases();

echo "Self-check passed.\n";
