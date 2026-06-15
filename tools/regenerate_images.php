<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$options = getopt('', ['all', 'large', 'thumbnails', 'photo-id:', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/regenerate_images.php --all\n";
    echo "  php tools/regenerate_images.php --large\n";
    echo "  php tools/regenerate_images.php --thumbnails\n";
    echo "  php tools/regenerate_images.php --all --photo-id=123\n";
    echo "  php tools/regenerate_images.php --all --dry-run\n";
    exit(0);
}

$regenerateLarge = isset($options['all']) || isset($options['large']);
$regenerateThumbnails = isset($options['all']) || isset($options['thumbnails']);
$dryRun = isset($options['dry-run']);
$photoId = isset($options['photo-id']) ? filter_var($options['photo-id'], FILTER_VALIDATE_INT) : null;

if (!$regenerateLarge && !$regenerateThumbnails) {
    fwrite(STDERR, "Вкажіть --all, --large або --thumbnails.\n");
    exit(1);
}

if ($photoId === false || (is_int($photoId) && $photoId < 1)) {
    fwrite(STDERR, "Некоректний --photo-id.\n");
    exit(1);
}

function regenerate_original_path(array $photo): ?string
{
    $filename = (string) ($photo['filename'] ?? '');

    return safe_existing_storage_file_path('originals', $filename)
        ?? safe_existing_upload_file_path('originals', $filename);
}

function regenerate_atomic_jpeg(callable $callback, string $destination): void
{
    $dir = dirname($destination);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Не вдалося створити папку: ' . $dir);
    }

    $tmp = tempnam($dir, basename($destination) . '.tmp.');
    if ($tmp === false) {
        throw new RuntimeException('Не вдалося створити тимчасовий файл у ' . $dir);
    }

    try {
        $callback($tmp);
        if (!rename($tmp, $destination)) {
            throw new RuntimeException('Не вдалося замінити файл: ' . $destination);
        }
    } catch (Throwable $exception) {
        if (is_file($tmp)) {
            @unlink($tmp);
        }

        throw $exception;
    }
}

function regenerate_photo_dimensions(string $originalPath, mixed $orientation): array
{
    $info = @getimagesize($originalPath);
    if ($info === false) {
        return [null, null];
    }

    return oriented_image_dimensions((int) $info[0], (int) $info[1], $orientation);
}

try {
    $sql = 'SELECT id, filename, thumbnail_filename, exif_json FROM photos';
    $params = [];

    if (is_int($photoId)) {
        $sql .= ' WHERE id = :id';
        $params['id'] = $photoId;
    }

    $sql .= ' ORDER BY id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $photos = $stmt->fetchAll();
} catch (Throwable $exception) {
    app_log_exception($exception, 'Regenerate images DB query failed');
    fwrite(STDERR, "Не вдалося отримати список фотографій із БД.\n");
    exit(1);
}

$processed = 0;
$updated = 0;
$skipped = 0;
$failed = 0;

foreach ($photos as $photo) {
    $processed++;
    $id = (int) $photo['id'];
    $filename = (string) $photo['filename'];
    $thumbnailFilename = (string) $photo['thumbnail_filename'];
    $originalPath = regenerate_original_path($photo);

    if ($originalPath === null) {
        $skipped++;
        echo "[SKIP] #{$id}: оригінал не знайдено ({$filename})\n";
        continue;
    }

    try {
        $exif = read_photo_exif($originalPath);
        $orientation = $exif['orientation'];

        if ($dryRun) {
            echo "[DRY] #{$id}: would regenerate" . ($regenerateLarge ? ' large' : '') . ($regenerateThumbnails ? ' thumbnail' : '') . "\n";
            continue;
        }

        if ($regenerateLarge) {
            $largePath = uploads_path('large', $filename);
            regenerate_atomic_jpeg(
                static function (string $tmp) use ($originalPath, $orientation): void {
                    create_large_image($originalPath, $tmp, $orientation);
                },
                $largePath
            );
        }

        if ($regenerateThumbnails) {
            $thumbnailPath = uploads_path('thumbnails', $thumbnailFilename);
            regenerate_atomic_jpeg(
                static function (string $tmp) use ($originalPath, $orientation): void {
                    create_thumbnail($originalPath, $tmp, $orientation);
                },
                $thumbnailPath
            );
        }

        [$width, $height] = regenerate_photo_dimensions($originalPath, $orientation);
        if ($width !== null && $height !== null) {
            $updateStmt = db()->prepare('UPDATE photos SET width = :width, height = :height WHERE id = :id');
            $updateStmt->execute([
                'width' => $width,
                'height' => $height,
                'id' => $id,
            ]);
        }

        $updated++;
        echo "[OK] #{$id}: regenerated {$filename}\n";
    } catch (Throwable $exception) {
        $failed++;
        app_log_exception($exception, 'Regenerate image #' . $id . ' failed');
        echo "[FAIL] #{$id}: " . $exception->getMessage() . "\n";
    }
}

echo "\nDone. Processed: {$processed}, updated: {$updated}, skipped: {$skipped}, failed: {$failed}.\n";
exit($failed > 0 ? 1 : 0);
