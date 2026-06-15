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

function check_required_extensions(): void
{
    $missing = missing_php_extensions();

    assert_true(empty($missing), 'Missing PHP extensions: ' . implode(', ', $missing));
}

function check_config_files(): void
{
    assert_true(is_file(project_root_path('config' . DIRECTORY_SEPARATOR . 'config.php')), 'config/config.php missing.');
    assert_true(is_file(project_root_path('config' . DIRECTORY_SEPARATOR . 'database.example.php')), 'config/database.example.php missing.');
    assert_true(is_file(project_root_path('config' . DIRECTORY_SEPARATOR . 'database.php')), 'config/database.php missing.');
    assert_true((string) app_config()['APP_URL'] !== '', 'APP_URL is empty.');
}

function check_required_directories(): void
{
    $requiredDirectories = [
        project_root_path('app' . DIRECTORY_SEPARATOR . 'includes'),
        project_root_path('config'),
        project_root_path('database'),
        public_path(),
        public_path('admin'),
        public_path('assets'),
        public_path('uploads'),
        uploads_path('originals'),
        uploads_path('large'),
        uploads_path('thumbnails'),
        storage_path(),
        originals_path(),
        trash_path(),
        storage_path('logs'),
        storage_path('sessions'),
        project_root_path('tools'),
        project_root_path('tools' . DIRECTORY_SEPARATOR . 'lib'),
    ];

    foreach ($requiredDirectories as $directory) {
        assert_true(is_dir($directory), 'Required directory missing: ' . $directory);
    }
}

function check_writable_directories(): void
{
    $writableDirectories = [
        originals_path(),
        trash_path(),
        storage_path('logs'),
        storage_path('sessions'),
        uploads_path('large'),
        uploads_path('thumbnails'),
    ];

    foreach ($writableDirectories as $directory) {
        assert_true(is_writable($directory), 'Directory is not writable: ' . $directory);
    }
}

function check_upload_protection_files(): void
{
    assert_true(is_file(public_path('uploads' . DIRECTORY_SEPARATOR . '.htaccess')), 'public/uploads/.htaccess missing.');
    assert_true(is_file(uploads_path('originals', '.htaccess')), 'public/uploads/originals/.htaccess missing.');
}

function check_required_tool_files(): void
{
    $toolFiles = [
        project_root_path('tools' . DIRECTORY_SEPARATOR . 'build_release.php'),
        project_root_path('tools' . DIRECTORY_SEPARATOR . 'backup.php'),
        project_root_path('tools' . DIRECTORY_SEPARATOR . 'regenerate_images.php'),
        project_root_path('tools' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SimpleZipWriter.php'),
        public_path('admin' . DIRECTORY_SEPARATOR . 'health.php'),
        public_path('admin' . DIRECTORY_SEPARATOR . 'download.php'),
    ];

    foreach ($toolFiles as $file) {
        assert_true(is_file($file), 'Required tool/page missing: ' . $file);
    }
}

function check_gitkeep_files(): void
{
    $gitkeepFiles = [
        originals_path('.gitkeep'),
        trash_path('.gitkeep'),
        storage_path('logs' . DIRECTORY_SEPARATOR . '.gitkeep'),
        storage_path('sessions' . DIRECTORY_SEPARATOR . '.gitkeep'),
        uploads_path('large', '.gitkeep'),
        uploads_path('thumbnails', '.gitkeep'),
        uploads_path('originals', '.gitkeep'),
    ];

    foreach ($gitkeepFiles as $file) {
        assert_true(is_file($file), 'Required .gitkeep missing: ' . $file);
    }
}

check_required_extensions();
check_config_files();
check_required_directories();
check_required_tool_files();
check_writable_directories();
check_upload_protection_files();
check_gitkeep_files();
check_csrf_cases();
check_orientation_cases();

echo "Self-check passed.\n";
