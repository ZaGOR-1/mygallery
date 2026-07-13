<?php

declare(strict_types=1);

function unlink_file_with_log(?string $path, string $context): bool
{
    if (!is_string($path) || $path === '' || !is_file($path)) {
        return true;
    }

    if (@unlink($path)) {
        return true;
    }

    app_log($context . ': failed to delete ' . basename($path));

    return false;
}

function uploads_path(string $folder, string $filename = ''): string
{
    $path = public_path('uploads' . DIRECTORY_SEPARATOR . $folder);

    return $filename === '' ? $path : $path . DIRECTORY_SEPARATOR . $filename;
}

function photo_media_id(array $photo): int
{
    if (array_key_exists('photo_id', $photo)) {
        return max(0, (int) $photo['photo_id']);
    }

    return max(0, (int) ($photo['id'] ?? 0));
}

function photo_media_url(array $photo, string $variant, string $format = 'jpg', ?string $token = null): string
{
    return url_with_query('media.php', [
        'id' => photo_media_id($photo),
        'variant' => $variant,
        'format' => $format,
        'token' => $token,
    ]);
}

function originals_path(string $filename = ''): string
{
    $path = storage_path('originals');

    return $filename === '' ? $path : $path . DIRECTORY_SEPARATOR . $filename;
}

function trash_path(string $filename = ''): string
{
    $path = storage_path('trash');

    return $filename === '' ? $path : $path . DIRECTORY_SEPARATOR . $filename;
}

function valid_photo_filename(string $filename): bool
{
    return preg_match('/\A[a-f0-9]{32}\.(jpg|webp|avif)\z/', $filename) === 1;
}

function valid_trash_photo_filename(string $filename): bool
{
    return preg_match('/\A[a-f0-9]{32}-[0-9]+-[a-f0-9]{32}\.(jpg|webp|avif)\z/', $filename) === 1;
}

function same_filesystem_path(string $left, string $right): bool
{
    $left = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $left), DIRECTORY_SEPARATOR);
    $right = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $right), DIRECTORY_SEPARATOR);

    if (PHP_OS_FAMILY === 'Windows') {
        return strtolower($left) === strtolower($right);
    }

    return $left === $right;
}

function safe_upload_file_path(string $folder, string $filename): ?string
{
    if (!valid_photo_filename($filename)) {
        return null;
    }

    $basePath = realpath(uploads_path($folder));

    if ($basePath === false) {
        return null;
    }

    return $basePath . DIRECTORY_SEPARATOR . $filename;
}

function safe_existing_upload_file_path(string $folder, string $filename): ?string
{
    if (!valid_photo_filename($filename)) {
        return null;
    }

    $basePath = realpath(uploads_path($folder));

    if ($basePath === false) {
        return null;
    }

    $filePath = realpath($basePath . DIRECTORY_SEPARATOR . $filename);

    if ($filePath === false) {
        return null;
    }

    $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($filePath, $basePath) ? $filePath : null;
}

function safe_storage_file_path(string $folder, string $filename): ?string
{
    if (!valid_photo_filename($filename)) {
        return null;
    }

    $basePath = realpath(storage_path($folder));

    if ($basePath === false) {
        return null;
    }

    return $basePath . DIRECTORY_SEPARATOR . $filename;
}

function safe_existing_storage_file_path(string $folder, string $filename): ?string
{
    if (!valid_photo_filename($filename)) {
        return null;
    }

    $basePath = realpath(storage_path($folder));

    if ($basePath === false) {
        return null;
    }

    $filePath = realpath($basePath . DIRECTORY_SEPARATOR . $filename);

    if ($filePath === false) {
        return null;
    }

    $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($filePath, $basePath) ? $filePath : null;
}

function safe_trash_file_path(string $filename): ?string
{
    if (!valid_trash_photo_filename($filename)) {
        return null;
    }

    $basePath = realpath(trash_path());

    if ($basePath === false) {
        return null;
    }

    return $basePath . DIRECTORY_SEPARATOR . $filename;
}

function safe_existing_trash_file_path(string $filename): ?string
{
    $path = safe_trash_file_path($filename);

    return $path !== null && is_file($path) ? $path : null;
}

function ensure_upload_folders(): array
{
    $errors = [];
    $folders = [
        originals_path() => private_directory_mode(),
        trash_path() => private_directory_mode(),
        uploads_path('large') => shared_directory_mode(),
        uploads_path('thumbnails') => shared_directory_mode(),
    ];

    foreach ($folders as $folder => $mode) {
        if (!ensure_directory($folder, $mode)) {
            $errors[] = 'Папка для завантажень недоступна або має небезпечні права: ' . basename($folder) . '.';
        }
    }

    return $errors;
}

function safe_original_name(string $name): string
{
    // Browsers may submit either POSIX paths or Windows fake paths. Normalize
    // both separators before selecting the final segment on every OS.
    $name = str_replace('\\', '/', $name);
    $segments = explode('/', $name);
    $name = (string) end($segments);
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
    $name = trim($name);

    if ($name === '' || $name === '.' || $name === '..') {
        return 'photo.jpg';
    }

    return text_limit($name, 255);
}

function size_to_bytes(string $value): int
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $unit = strtolower($value[strlen($value) - 1]);
    $number = (float) $value;

    if ($unit === 'g') {
        $number *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $number *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $number *= 1024;
    }

    return (int) round($number);
}

function bytes_for_display(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1, '.', ''), '0'), '.') . ' МБ';
    }

    return rtrim(rtrim(number_format($bytes / 1024, 1, '.', ''), '0'), '.') . ' КБ';
}

function upload_server_limit(): int
{
    $limits = [
        size_to_bytes((string) ini_get('upload_max_filesize')),
        size_to_bytes((string) ini_get('post_max_size')),
    ];

    $limits = array_filter($limits, static fn (int $limit): bool => $limit > 0);

    return empty($limits) ? 0 : min($limits);
}

function memory_limit_bytes(): int
{
    $limit = size_to_bytes((string) ini_get('memory_limit'));

    return $limit > 0 ? $limit : 0;
}

function validate_gd_memory_limit(int $width, int $height): array
{
    $memoryLimit = memory_limit_bytes();

    if ($memoryLimit === 0) {
        return [];
    }

    $pixels = $width * $height;
    $bytesPerPixel = 4;
    $sourceImage = $pixels * $bytesPerPixel;
    
    $maxLargeW = (int) app_config()['LARGE_MAX_WIDTH'];
    $largeWidth = $width > $maxLargeW ? $maxLargeW : $width;
    $largeHeight = $width > $maxLargeW ? (int) ($height * ($maxLargeW / $width)) : $height;
    $largeImage = $largeWidth * $largeHeight * $bytesPerPixel;

    $thumbWidth = $width > 600 ? 600 : $width;
    $thumbHeight = $width > 600 ? (int) ($height * (600 / $width)) : $height;
    $thumbnailImage = $thumbWidth * $thumbHeight * $bytesPerPixel;

    $estimatedNeed = (int) (($sourceImage * 2 + $largeImage + $thumbnailImage) * 1.35);
    $available = $memoryLimit - memory_get_usage(true);

    if ($available > 0 && $estimatedNeed > $available) {
        return ['Зображення завелике для обробки у поточному memory_limit PHP. Спробуйте менший JPEG або збільшіть memory_limit.'];
    }

    return [];
}

function validate_image_limits(array $imageInfo): array
{
    $config = app_config();
    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    $maxWidth = (int) $config['MAX_IMAGE_WIDTH'];
    $maxHeight = (int) $config['MAX_IMAGE_HEIGHT'];
    $maxPixels = (int) $config['MAX_IMAGE_PIXELS'];

    if ($width < 1 || $height < 1) {
        return ['Не вдалося визначити розміри JPEG-файла.'];
    }

    $errors = [];
    $pixels = $width * $height;

    if ($width > $maxWidth || $height > $maxHeight || $pixels > $maxPixels) {
        $errors[] = sprintf(
            'Зображення завелике за розмірами. Максимум: %dx%d або %s МП.',
            $maxWidth,
            $maxHeight,
            rtrim(rtrim(number_format($maxPixels / 1000000, 1, '.', ''), '0'), '.')
        );
    }

    $errors = array_merge($errors, validate_gd_memory_limit($width, $height));

    return $errors;
}

function random_photo_name(): string
{
    return bin2hex(random_bytes(16)) . '.jpg';
}

function exif_fraction_to_float(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }

    $value = trim((string) $value);

    if (str_contains($value, '/')) {
        [$top, $bottom] = array_pad(explode('/', $value, 2), 2, '0');
        $top = (float) $top;
        $bottom = (float) $bottom;

        return $bottom == 0.0 ? null : $top / $bottom;
    }

    return is_numeric($value) ? (float) $value : null;
}

function exif_display_value(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'Немає даних';
    }

    if (is_array($value)) {
        return 'Немає даних';
    }

    return (string) $value;
}

function format_aperture(mixed $value): string
{
    $number = exif_fraction_to_float($value);

    return $number === null ? 'Немає даних' : 'f/' . rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
}

function format_focal_length(mixed $value): string
{
    $number = exif_fraction_to_float($value);

    return $number === null ? 'Немає даних' : rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.') . ' мм';
}

function format_exposure_time(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'Немає даних';
    }

    return (string) $value . ' с';
}

function parse_exif_date(mixed $value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y:m:d H:i:s', $value);

    return $date instanceof DateTime ? $date->format('Y-m-d H:i:s') : null;
}

function read_photo_exif(string $path): array
{
    $exif = [];

    if (function_exists('exif_read_data')) {
        $data = @exif_read_data($path, null, true);
        $exif = is_array($data) ? $data : [];
    }

    $ifd0 = is_array($exif['IFD0'] ?? null) ? $exif['IFD0'] : [];
    $exifSection = is_array($exif['EXIF'] ?? null) ? $exif['EXIF'] : [];
    $computed = is_array($exif['COMPUTED'] ?? null) ? $exif['COMPUTED'] : [];

    $width = $computed['Width'] ?? null;
    $height = $computed['Height'] ?? null;
    $orientation = $ifd0['Orientation'] ?? null;

    return [
        'raw' => $exif,
        'camera_make' => is_string($ifd0['Make'] ?? null) ? trim($ifd0['Make']) : null,
        'camera_model' => is_string($ifd0['Model'] ?? null) ? trim($ifd0['Model']) : null,
        'lens_model' => is_string($exifSection['LensModel'] ?? $exifSection['UndefinedTag:0xA434'] ?? null)
            ? trim($exifSection['LensModel'] ?? $exifSection['UndefinedTag:0xA434'])
            : null,
        'taken_at' => parse_exif_date($exifSection['DateTimeOriginal'] ?? $ifd0['DateTime'] ?? null),
        'iso' => $exifSection['ISOSpeedRatings'] ?? null,
        'aperture' => $exifSection['FNumber'] ?? null,
        'exposure_time' => $exifSection['ExposureTime'] ?? null,
        'focal_length' => $exifSection['FocalLength'] ?? null,
        'exposure_mode' => $exifSection['ExposureMode'] ?? null,
        'flash' => $exifSection['Flash'] ?? null,
        'orientation' => $orientation,
        'width' => is_numeric($width) ? (int) $width : null,
        'height' => is_numeric($height) ? (int) $height : null,
    ];
}

function normalized_exif_for_display(?string $json, array $photo = []): array
{
    $raw = $json ? json_decode($json, true) : [];
    $ifd0 = is_array($raw['IFD0'] ?? null) ? $raw['IFD0'] : [];
    $exif = is_array($raw['EXIF'] ?? null) ? $raw['EXIF'] : [];

    return [
        'Виробник камери' => exif_display_value($photo['camera_make'] ?? $ifd0['Make'] ?? null),
        'Модель камери' => exif_display_value($photo['camera_model'] ?? $ifd0['Model'] ?? null),
        'Об’єктив' => exif_display_value($photo['lens_model'] ?? $exif['LensModel'] ?? $exif['UndefinedTag:0xA434'] ?? null),
        'Дата і час зйомки' => exif_display_value($photo['taken_at'] ?? null),
        'ISO' => exif_display_value($exif['ISOSpeedRatings'] ?? null),
        'Діафрагма' => format_aperture($exif['FNumber'] ?? null),
        'Витримка' => format_exposure_time($exif['ExposureTime'] ?? null),
        'Фокусна відстань' => format_focal_length($exif['FocalLength'] ?? null),
        'Режим експозиції' => exif_display_value($exif['ExposureMode'] ?? null),
        'Спалах' => exif_display_value($exif['Flash'] ?? null),
        'Орієнтація' => exif_display_value($ifd0['Orientation'] ?? null),
        'Ширина' => isset($photo['width']) ? (string) $photo['width'] . ' px' : 'Немає даних',
        'Висота' => isset($photo['height']) ? (string) $photo['height'] . ' px' : 'Немає даних',
    ];
}

function create_image_from_jpeg(string $path): GdImage|false
{
    return @imagecreatefromjpeg($path);
}

function apply_orientation(GdImage $image, mixed $orientation): GdImage
{
    $orientation = (int) $orientation;

    if ($orientation === 2) {
        if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 2.');
        }

        return $image;
    }

    if ($orientation === 3) {
        $rotated = imagerotate($image, 180, 0);
        if (!$rotated instanceof GdImage) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 3.');
        }

        return $rotated;
    }

    if ($orientation === 4) {
        if (!imageflip($image, IMG_FLIP_VERTICAL)) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 4.');
        }

        return $image;
    }

    if ($orientation === 5) {
        if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 5 flip.');
        }

        $rotated = imagerotate($image, 90, 0);
        if (!$rotated instanceof GdImage) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 5 rotate.');
        }

        return $rotated;
    }

    if ($orientation === 6) {
        $rotated = imagerotate($image, -90, 0);
        if (!$rotated instanceof GdImage) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 6.');
        }

        return $rotated;
    }

    if ($orientation === 7) {
        if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 7 flip.');
        }

        $rotated = imagerotate($image, -90, 0);
        if (!$rotated instanceof GdImage) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 7 rotate.');
        }

        return $rotated;
    }

    if ($orientation === 8) {
        $rotated = imagerotate($image, 90, 0);
        if (!$rotated instanceof GdImage) {
            throw new RuntimeException('Не вдалося застосувати EXIF orientation 8.');
        }

        return $rotated;
    }

    return $image;
}

function oriented_image_dimensions(int $width, int $height, mixed $orientation): array
{
    $orientation = (int) $orientation;

    if (in_array($orientation, [5, 6, 7, 8], true)) {
        return [$height, $width];
    }

    return [$width, $height];
}

function move_uploaded_original(string $source, string $destination): void
{
    if (!move_uploaded_file($source, $destination)) {
        throw new RuntimeException('Не вдалося зберегти оригінальний JPEG-файл.');
    }
    if (!enforce_private_file_permissions($destination)) {
        @unlink($destination);
        throw new RuntimeException('Не вдалося встановити приватні права для оригінального JPEG-файлу.');
    }
}

function create_resized_jpeg(string $source, string $destination, int $maxWidth, int $quality): void
{
    $image = create_image_from_jpeg($source);

    if (!$image instanceof GdImage) {
        throw new RuntimeException('Не вдалося створити зменшену копію.');
    }

    $width = imagesx($image);
    $height = imagesy($image);

    if ($width < 1 || $height < 1) {
        imagedestroy($image);
        throw new RuntimeException('Некоректні розміри JPEG-зображення.');
    }

    $newWidth = min($maxWidth, $width);
    $newHeight = (int) round($height * ($newWidth / $width));

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    if (!$resized instanceof GdImage) {
        imagedestroy($image);
        throw new RuntimeException('Не вдалося створити GD canvas для зменшеної копії.');
    }

    if (!imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
        imagedestroy($resized);
        imagedestroy($image);
        throw new RuntimeException('Не вдалося перемасштабувати JPEG-зображення.');
    }

    if (!imagejpeg($resized, $destination, $quality)) {
        imagedestroy($resized);
        imagedestroy($image);
        throw new RuntimeException('Не вдалося зберегти зменшену копію.');
    }
    if (!enforce_shared_file_permissions($destination)) {
        @unlink($destination);
        imagedestroy($resized);
        imagedestroy($image);
        throw new RuntimeException('Не вдалося встановити безпечні права для зменшеної копії.');
    }

    imagedestroy($resized);
    imagedestroy($image);
}

function create_oriented_resized_jpeg(string $source, string $destination, int $maxWidth, int $quality, mixed $orientation): void
{
    $image = create_image_from_jpeg($source);

    if (!$image instanceof GdImage) {
        throw new RuntimeException('Не вдалося створити зменшену копію.');
    }

    $oriented = apply_orientation($image, $orientation);
    $width = imagesx($oriented);
    $height = imagesy($oriented);

    if ($width < 1 || $height < 1) {
        if ($oriented !== $image) {
            imagedestroy($oriented);
        }

        imagedestroy($image);
        throw new RuntimeException('Некоректні розміри JPEG-зображення.');
    }

    $newWidth = min($maxWidth, $width);
    $newHeight = (int) round($height * ($newWidth / $width));
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    if (!$resized instanceof GdImage) {
        if ($oriented !== $image) {
            imagedestroy($oriented);
        }

        imagedestroy($image);
        throw new RuntimeException('Не вдалося створити GD canvas для зменшеної копії.');
    }

    if (!imagecopyresampled($resized, $oriented, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
        imagedestroy($resized);
        if ($oriented !== $image) {
            imagedestroy($oriented);
        }

        imagedestroy($image);
        throw new RuntimeException('Не вдалося перемасштабувати JPEG-зображення.');
    }

    if (!imagejpeg($resized, $destination, $quality)) {
        imagedestroy($resized);
        if ($oriented !== $image) {
            imagedestroy($oriented);
        }

        imagedestroy($image);
        throw new RuntimeException('Не вдалося зберегти зменшену копію.');
    }
    if (!enforce_shared_file_permissions($destination)) {
        @unlink($destination);
        imagedestroy($resized);
        if ($oriented !== $image) {
            imagedestroy($oriented);
        }
        imagedestroy($image);
        throw new RuntimeException('Не вдалося встановити безпечні права для зменшеної копії.');
    }

    imagedestroy($resized);
    if ($oriented !== $image) {
        imagedestroy($oriented);
    }

    imagedestroy($image);
}

function create_thumbnail(string $source, string $destination, mixed $orientation, int $maxWidth = 600): void
{
    create_oriented_resized_jpeg($source, $destination, $maxWidth, 90, $orientation);
}

function create_large_image(string $source, string $destination, mixed $orientation): void
{
    create_oriented_resized_jpeg($source, $destination, (int) app_config()['LARGE_MAX_WIDTH'], 95, $orientation);
}

/**
 * @param null|callable(GdImage, GdImage, int, int, int, int, int, int, int, int): bool $resampler
 */
function get_image_dominant_color(string $filepath, ?callable $resampler = null): ?string
{
    $img = @imagecreatefromjpeg($filepath);
    if (!$img) {
        return null;
    }

    $tmp = imagecreatetruecolor(1, 1);
    if (!$tmp) {
        imagedestroy($img);
        return null;
    }

    $resampler ??= static fn (
        GdImage $destination,
        GdImage $source,
        int $destinationX,
        int $destinationY,
        int $sourceX,
        int $sourceY,
        int $destinationWidth,
        int $destinationHeight,
        int $sourceWidth,
        int $sourceHeight
    ): bool => imagecopyresampled(
        $destination,
        $source,
        $destinationX,
        $destinationY,
        $sourceX,
        $sourceY,
        $destinationWidth,
        $destinationHeight,
        $sourceWidth,
        $sourceHeight
    );
    if (!$resampler($tmp, $img, 0, 0, 0, 0, 1, 1, imagesx($img), imagesy($img))) {
        imagedestroy($img);
        imagedestroy($tmp);
        return null;
    }
    $color = imagecolorat($tmp, 0, 0);
    if (!is_int($color)) {
        imagedestroy($img);
        imagedestroy($tmp);
        return null;
    }

    $r = ($color >> 16) & 0xFF;
    $g = ($color >> 8) & 0xFF;
    $b = $color & 0xFF;

    imagedestroy($img);
    imagedestroy($tmp);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Derivative (.webp/.avif) path that would be produced for a given JPEG path.
 * Used both when creating derivatives and when cleaning up after a failed upload.
 */
function derivative_path(string $jpegPath, string $extension): string
{
    return preg_replace('/\.jpe?g$/i', '.' . $extension, $jpegPath);
}

/**
 * Publishes a generated derivative with a same-directory atomic rename.
 *
 * The previous derivative remains readable while a replacement is encoded. If
 * the current source cannot produce a valid replacement, the previous file is
 * removed so media.php cannot serve bytes that no longer match the JPEG.
 *
 * @param callable(GdImage, string, int): bool $encoder
 */
function create_atomic_image_derivative(
    string $jpegPath,
    string $extension,
    int $quality,
    callable $encoder,
    int $expectedImageType
): bool {
    $destination = derivative_path($jpegPath, $extension);
    $removeStaleDestination = static function () use ($destination, $extension): void {
        if (is_link($destination)) {
            if (!@unlink($destination)) {
                app_log('Failed stale ' . strtoupper($extension) . ' derivative cleanup: failed to delete ' . basename($destination));
            }
        } elseif (is_file($destination)) {
            unlink_file_with_log($destination, 'Failed stale ' . strtoupper($extension) . ' derivative cleanup');
        }
    };

    if (!is_file($jpegPath) || is_link($jpegPath)) {
        $removeStaleDestination();
        return false;
    }

    if (!is_dir(dirname($destination))) {
        $removeStaleDestination();
        return false;
    }
    $temporary = tempnam(dirname($destination), '.derivative_');
    if ($temporary === false) {
        $removeStaleDestination();
        return false;
    }

    $image = null;
    try {
        $image = create_image_from_jpeg($jpegPath);
        if (!$image instanceof GdImage || $encoder($image, $temporary, $quality) !== true) {
            $removeStaleDestination();
            return false;
        }

        $size = filesize($temporary);
        $imageInfo = @getimagesize($temporary);
        if (!is_int($size) || $size < 1 || !is_array($imageInfo) || (int) ($imageInfo[2] ?? 0) !== $expectedImageType) {
            $removeStaleDestination();
            return false;
        }
        if (!enforce_shared_file_permissions($temporary)) {
            $removeStaleDestination();
            return false;
        }
        if (is_link($destination)) {
            $removeStaleDestination();
        }
        if (!rename($temporary, $destination)) {
            $removeStaleDestination();
            return false;
        }

        $temporary = '';
        return true;
    } catch (Throwable $exception) {
        app_log_exception($exception, strtoupper($extension) . ' derivative generation failed');
        $removeStaleDestination();
        return false;
    } finally {
        if ($image instanceof GdImage) {
            imagedestroy($image);
        }
        if ($temporary !== '' && (is_file($temporary) || is_link($temporary))) {
            unlink_file_with_log($temporary, 'Failed temporary derivative cleanup');
        }
    }
}

function create_webp_copy(string $jpegPath): void
{
    if (!function_exists('imagewebp')) {
        $webpPath = derivative_path($jpegPath, 'webp');
        if (is_link($webpPath)) {
            @unlink($webpPath);
        } elseif (is_file($webpPath)) {
            unlink_file_with_log($webpPath, 'Failed stale WebP derivative cleanup');
        }
        return;
    }

    create_atomic_image_derivative(
        $jpegPath,
        'webp',
        (int) (app_config()['IMAGE_QUALITY_WEBP'] ?? 85),
        static fn (GdImage $image, string $path, int $quality): bool => imagewebp($image, $path, $quality),
        IMAGETYPE_WEBP
    );
}

function create_avif_copy(string $jpegPath): void
{
    if (!function_exists('imageavif')) {
        $avifPath = derivative_path($jpegPath, 'avif');
        if (is_link($avifPath)) {
            @unlink($avifPath);
        } elseif (is_file($avifPath)) {
            unlink_file_with_log($avifPath, 'Failed stale AVIF derivative cleanup');
        }
        return;
    }

    create_atomic_image_derivative(
        $jpegPath,
        'avif',
        (int) (app_config()['IMAGE_QUALITY_AVIF'] ?? 65),
        static fn (GdImage $image, string $path, int $quality): bool => imageavif($image, $path, $quality),
        IMAGETYPE_AVIF
    );
}

function photo_display_url(array $photo, ?string $token = null): string
{
    $filename = (string) $photo['filename'];

    if (safe_existing_upload_file_path('large', $filename) !== null) {
        return photo_media_url($photo, 'large', 'jpg', $token);
    }

    return photo_media_url($photo, 'thumbnail', 'jpg', $token);
}

function photo_responsive_srcset(array $photo, ?string $token = null): string
{
    $items = [];
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');
    $filename = (string) ($photo['filename'] ?? '');

    if ($thumbnail !== '' && safe_existing_upload_file_path('thumbnails', $thumbnail) !== null) {
        $items[] = photo_media_url($photo, 'thumbnail', 'jpg', $token) . ' 600w';
    }

    if ($filename !== '' && safe_existing_upload_file_path('large', $filename) !== null) {
        $largeWidth = (int) ($photo['width'] ?? 0);
        $largeWidth = $largeWidth > 0 ? min($largeWidth, (int) app_config()['LARGE_MAX_WIDTH']) : (int) app_config()['LARGE_MAX_WIDTH'];

        if ($largeWidth > 600) {
            $items[] = photo_media_url($photo, 'large', 'jpg', $token) . ' ' . $largeWidth . 'w';
        }
    }

    return implode(', ', $items);
}

function photo_cover_srcset(array $photo, ?string $token = null): string
{
    $filename = (string) ($photo['filename'] ?? '');

    if ($filename !== '' && safe_existing_upload_file_path('large', $filename) !== null) {
        $largeWidth = (int) ($photo['width'] ?? 0);
        $largeWidth = $largeWidth > 0 ? min($largeWidth, (int) app_config()['LARGE_MAX_WIDTH']) : (int) app_config()['LARGE_MAX_WIDTH'];

        return photo_media_url($photo, 'large', 'jpg', $token) . ' ' . $largeWidth . 'w';
    }

    return photo_responsive_srcset($photo, $token);
}

function photo_responsive_srcset_next_gen(array $photo, string $extension, ?string $token = null): string
{
    $items = [];
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');
    $filename = (string) ($photo['filename'] ?? '');

    if ($thumbnail !== '') {
        $nextGenThumb = preg_replace('/\.jpe?g$/i', '.' . $extension, $thumbnail);
        if (safe_existing_upload_file_path('thumbnails', $nextGenThumb) !== null) {
            $items[] = photo_media_url($photo, 'thumbnail', $extension, $token) . ' 600w';
        }
    }

    if ($filename !== '') {
        $nextGenLarge = preg_replace('/\.jpe?g$/i', '.' . $extension, $filename);
        if (safe_existing_upload_file_path('large', $nextGenLarge) !== null) {
            $largeWidth = (int) ($photo['width'] ?? 0);
            if ($largeWidth > 0) {
                $items[] = photo_media_url($photo, 'large', $extension, $token) . ' ' . $largeWidth . 'w';
            }
        }
    }

    return implode(', ', $items);
}

function photo_cover_srcset_next_gen(array $photo, string $extension, ?string $token = null): string
{
    $filename = (string) ($photo['filename'] ?? '');

    if ($filename !== '') {
        $nextGenLarge = preg_replace('/\.jpe?g$/i', '.' . $extension, $filename);
        if (safe_existing_upload_file_path('large', $nextGenLarge) !== null) {
            $largeWidth = (int) ($photo['width'] ?? 0);
            $largeWidth = $largeWidth > 0 ? min($largeWidth, (int) app_config()['LARGE_MAX_WIDTH']) : (int) app_config()['LARGE_MAX_WIDTH'];

            return photo_media_url($photo, 'large', $extension, $token) . ' ' . $largeWidth . 'w';
        }
    }

    return photo_responsive_srcset_next_gen($photo, $extension, $token);
}

function photo_card_sizes(): string
{
    return '(max-width: 700px) 100vw, (max-width: 1100px) 50vw, 25vw';
}

function photo_view_sizes(): string
{
    return '(max-width: 900px) 100vw, 1200px';
}

function photo_file_paths(array $photo): array
{
    $paths = [];
    $filename = (string) ($photo['filename'] ?? '');
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');
    $storageOriginal = safe_existing_storage_file_path('originals', $filename);
    $legacyOriginal = safe_existing_upload_file_path('originals', $filename);

    foreach ([$storageOriginal, $legacyOriginal] as $path) {
        if ($path !== null) {
            $paths[] = $path;
        }
    }

    $large = safe_existing_upload_file_path('large', $filename);
    if ($large !== null) {
        $paths[] = $large;
        $largeWebp = preg_replace('/\.jpe?g$/i', '.webp', $large);
        if (is_file($largeWebp)) {
            $paths[] = $largeWebp;
        }
        $largeAvif = preg_replace('/\.jpe?g$/i', '.avif', $large);
        if (is_file($largeAvif)) {
            $paths[] = $largeAvif;
        }
    }

    $thumbnailPath = safe_existing_upload_file_path('thumbnails', $thumbnail);
    if ($thumbnailPath !== null) {
        $paths[] = $thumbnailPath;
        $thumbnailWebp = preg_replace('/\.jpe?g$/i', '.webp', $thumbnailPath);
        if (is_file($thumbnailWebp)) {
            $paths[] = $thumbnailWebp;
        }
        $thumbnailAvif = preg_replace('/\.jpe?g$/i', '.avif', $thumbnailPath);
        if (is_file($thumbnailAvif)) {
            $paths[] = $thumbnailAvif;
        }
    }

    return $paths;
}

function photo_file_reference_from_path(string $path): ?array
{
    $filename = basename($path);

    if (!valid_photo_filename($filename)) {
        return null;
    }

    $realPath = realpath($path);

    if ($realPath === false) {
        return null;
    }

    $locations = [
        ['area' => 'storage', 'folder' => 'originals', 'base' => originals_path()],
        ['area' => 'public', 'folder' => 'originals', 'base' => uploads_path('originals')],
        ['area' => 'public', 'folder' => 'large', 'base' => uploads_path('large')],
        ['area' => 'public', 'folder' => 'thumbnails', 'base' => uploads_path('thumbnails')],
    ];

    foreach ($locations as $location) {
        $basePath = realpath($location['base']);

        if ($basePath === false) {
            continue;
        }

        $expected = $basePath . DIRECTORY_SEPARATOR . $filename;

        if (same_filesystem_path($realPath, $expected)) {
            return [
                'area' => $location['area'],
                'folder' => $location['folder'],
                'filename' => $filename,
            ];
        }
    }

    return null;
}

function expected_photo_file_path(string $area, string $folder, string $filename): ?string
{
    if ($area === 'storage' && $folder === 'originals') {
        return safe_storage_file_path($folder, $filename);
    }

    if ($area === 'public' && in_array($folder, ['originals', 'large', 'thumbnails'], true)) {
        return safe_upload_file_path($folder, $filename);
    }

    return null;
}

function resolve_legacy_trash_entry(array $file): ?array
{
    $from = (string) ($file['from'] ?? '');
    $trash = (string) ($file['trash'] ?? '');
    $filename = basename($from);
    $trashFilename = basename($trash);

    if (!valid_photo_filename($filename) || !valid_trash_photo_filename($trashFilename)) {
        return null;
    }

    if (!str_ends_with($trashFilename, '-' . $filename)) {
        return null;
    }

    $trashDir = realpath(dirname($trash));
    $expectedTrashDir = realpath(trash_path());

    if ($trashDir === false || $expectedTrashDir === false || !same_filesystem_path($trashDir, $expectedTrashDir)) {
        return null;
    }

    $fromDir = realpath(dirname($from));

    if ($fromDir === false) {
        return null;
    }

    foreach ([
        ['area' => 'storage', 'folder' => 'originals', 'base' => originals_path()],
        ['area' => 'public', 'folder' => 'originals', 'base' => uploads_path('originals')],
        ['area' => 'public', 'folder' => 'large', 'base' => uploads_path('large')],
        ['area' => 'public', 'folder' => 'thumbnails', 'base' => uploads_path('thumbnails')],
    ] as $location) {
        $basePath = realpath($location['base']);

        if ($basePath !== false && same_filesystem_path($fromDir, $basePath)) {
            return [
                'from' => expected_photo_file_path($location['area'], $location['folder'], $filename),
                'trash' => safe_trash_file_path($trashFilename),
                'filename' => $filename,
                'trash_filename' => $trashFilename,
            ];
        }
    }

    return null;
}

function resolve_trash_manifest_entry(array $file): ?array
{
    $area = (string) ($file['area'] ?? '');
    $folder = (string) ($file['folder'] ?? '');
    $filename = (string) ($file['filename'] ?? '');
    $trashFilename = (string) ($file['trash_filename'] ?? '');

    if ($area !== '' || $folder !== '' || $filename !== '' || $trashFilename !== '') {
        if (!valid_photo_filename($filename) || !valid_trash_photo_filename($trashFilename)) {
            return null;
        }

        if (!str_ends_with($trashFilename, '-' . $filename)) {
            return null;
        }

        return [
            'from' => expected_photo_file_path($area, $folder, $filename),
            'trash' => safe_trash_file_path($trashFilename),
            'filename' => $filename,
            'trash_filename' => $trashFilename,
        ];
    }

    return resolve_legacy_trash_entry($file);
}

function photo_filename_errors(array $photo): array
{
    $errors = [];
    $files = [
        (string) ($photo['filename'] ?? ''),
        (string) ($photo['thumbnail_filename'] ?? ''),
    ];

    foreach ($files as $filename) {
        if (!valid_photo_filename($filename)) {
            $errors[] = 'Некоректне ім’я файла фотографії.';
        }
    }

    return array_values(array_unique($errors));
}

/** @param list<array<string, mixed>> $files */
function trash_manifest_contains_required_photo_files(array $photo, array $files): bool
{
    $filename = (string) ($photo['filename'] ?? '');
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');
    if (!valid_photo_filename($filename) || !valid_photo_filename($thumbnail) || $files === []) {
        return false;
    }

    $presentPaths = [];
    foreach ($files as $file) {
        $resolved = resolve_trash_manifest_entry($file);
        if ($resolved === null || !is_string($resolved['from'] ?? null)) {
            return false;
        }
        $presentPaths[] = (string) $resolved['from'];
    }
    $contains = static function (?string $expected) use ($presentPaths): bool {
        if ($expected === null) {
            return false;
        }
        foreach ($presentPaths as $present) {
            if (same_filesystem_path($present, $expected)) {
                return true;
            }
        }

        return false;
    };

    $hasOriginal = $contains(expected_photo_file_path('storage', 'originals', $filename))
        || $contains(expected_photo_file_path('public', 'originals', $filename));

    return $hasOriginal
        && $contains(expected_photo_file_path('public', 'large', $filename))
        && $contains(expected_photo_file_path('public', 'thumbnails', $thumbnail));
}

function validate_photo_files_deletable(array $photo): array
{
    $errors = photo_filename_errors($photo);

    if ($errors === []) {
        $filename = (string) $photo['filename'];
        $thumbnail = (string) $photo['thumbnail_filename'];
        if (safe_existing_storage_file_path('originals', $filename) === null
            && safe_existing_upload_file_path('originals', $filename) === null) {
            $errors[] = 'Відсутній обов’язковий оригінал фотографії.';
        }
        if (safe_existing_upload_file_path('large', $filename) === null) {
            $errors[] = 'Відсутня обов’язкова large-копія фотографії.';
        }
        if (safe_existing_upload_file_path('thumbnails', $thumbnail) === null) {
            $errors[] = 'Відсутня обов’язкова мініатюра фотографії.';
        }
    }

    foreach (photo_file_paths($photo) as $file) {
        $directory = dirname($file);

        if (!is_dir($directory) || !is_writable($directory)) {
            $errors[] = 'Немає права змінювати папку з файлом ' . basename($file) . '.';
        }
    }

    $trash = trash_path();
    if (!is_dir($trash) || !is_writable($trash)) {
        $errors[] = 'Папка storage/trash недоступна для запису.';
    }

    return $errors;
}

function delete_photo_files(array $photo): array
{
    $errors = [];

    foreach (photo_file_paths($photo) as $file) {
        if (is_file($file) && !@unlink($file)) {
            $errors[] = 'Не вдалося видалити файл ' . basename($file) . '.';
        }
    }

    return $errors;
}

function move_photo_files_to_trash(array $photo): array
{
    $operationId = bin2hex(random_bytes(16));
    $moved = [];
    $planned = [];

    foreach (photo_file_paths($photo) as $file) {
        $reference = photo_file_reference_from_path($file);

        if ($reference === null) {
            throw new RuntimeException('Некоректний шлях файла для видалення: ' . basename($file));
        }

        $trashName = $operationId . '-' . count($planned) . '-' . basename($file);
        $planned[] = [
            'from' => $file,
            'trash' => trash_path($trashName),
            'area' => $reference['area'],
            'folder' => $reference['folder'],
            'filename' => $reference['filename'],
            'trash_filename' => $trashName,
        ];
    }

    $manifestFiles = array_map(
        static fn (array $file): array => [
            'area' => $file['area'],
            'folder' => $file['folder'],
            'filename' => $file['filename'],
            'trash_filename' => $file['trash_filename'],
        ],
        $planned
    );
    if (!trash_manifest_contains_required_photo_files($photo, $manifestFiles)) {
        throw new RuntimeException('Журнал видалення не містить повного canonical media-набору.');
    }

    $tagNames = [];
    if (isset($photo['id'])) {
        try {
            $tags = get_photo_tags((int) $photo['id']);
            $tagNames = array_column($tags, 'name');
        } catch (Throwable $e) {
            app_log_exception($e, 'Could not read tags for trash manifest');
        }
    }

    $manifestPath = trash_path($operationId . '.json');
    $manifest = [
        'operation_id' => $operationId,
        'photo_id' => isset($photo['id']) ? (int) $photo['id'] : null,
        'created_at' => date('c'),
        'photo_data' => $photo,
        'tags' => $tagNames,
        'files' => $manifestFiles,
    ];

    if (private_file_put_contents($manifestPath, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        throw new RuntimeException('Не вдалося створити журнал видалення.');
    }

    foreach ($planned as $file) {
        if (!rename($file['from'], $file['trash'])) {
            restore_moved_photo_files(['files' => $moved, 'manifest' => $manifestPath]);
            throw new RuntimeException('Не вдалося перемістити файл у кошик: ' . basename((string) $file['from']));
        }

        $moved[] = $file;
    }

    return ['files' => $moved, 'manifest' => $manifestPath, 'operation_id' => $operationId];
}

/**
 * @param list<array<string, mixed>> $unresolvedEntries
 */
function rewrite_trash_manifest_unresolved(string $manifestPath, array $unresolvedEntries, string $status): bool
{
    if (!recover_interrupted_trash_manifest_update($manifestPath)) {
        return false;
    }

    $manifest = [];
    if (is_file($manifestPath)) {
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($decoded)) {
            $manifest = $decoded;
        }
    }

    $manifest['status'] = $status;
    $manifest['updated_at'] = date('c');
    $manifest['files'] = array_values(array_map(
        static fn (array $entry): array => array_intersect_key($entry, array_flip([
            'area', 'folder', 'filename', 'trash_filename', 'from', 'trash',
        ])),
        $unresolvedEntries
    ));

    try {
        $payload = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (Throwable) {
        return false;
    }

    $directory = dirname($manifestPath);
    $temporary = tempnam($directory, '.trash_manifest_');
    if ($temporary === false || private_file_put_contents($temporary, $payload, LOCK_EX) !== strlen($payload)) {
        if (is_string($temporary) && is_file($temporary)) {
            @unlink($temporary);
        }
        return false;
    }

    $previous = $manifestPath . '.previous';
    if (file_exists($previous) || is_link($previous)) {
        @unlink($temporary);
        return false;
    }
    if (is_file($manifestPath) && !rename($manifestPath, $previous)) {
        @unlink($temporary);
        return false;
    }
    if (!rename($temporary, $manifestPath)) {
        if (is_file($previous)) {
            @rename($previous, $manifestPath);
        }
        @unlink($temporary);
        return false;
    }
    if (is_file($previous) && !@unlink($previous)) {
        app_log('Trash manifest previous-version cleanup failed: ' . basename($previous));
    }

    return true;
}

function recover_interrupted_trash_manifest_update(string $manifestPath): bool
{
    $previous = $manifestPath . '.previous';
    if (is_link($manifestPath) || is_link($previous)) {
        return false;
    }
    if (is_file($manifestPath)) {
        if (is_file($previous) && !@unlink($previous)) {
            return false;
        }

        return true;
    }
    if (is_file($previous)) {
        return rename($previous, $manifestPath);
    }

    return true;
}

function recover_interrupted_trash_manifest_updates(): void
{
    foreach (glob(trash_path('*.json.previous')) ?: [] as $previous) {
        $manifestPath = substr($previous, 0, -strlen('.previous'));
        $operationId = pathinfo($manifestPath, PATHINFO_FILENAME);
        if (preg_match('/\A[a-f0-9]{32}\z/', $operationId) !== 1
            || !recover_interrupted_trash_manifest_update($manifestPath)) {
            throw new RuntimeException('Не вдалося відновити перерване оновлення trash manifest: ' . basename($previous));
        }
    }
}

function restore_moved_photo_files(array $trashOperation): array
{
    $errors = [];
    $movedFiles = $trashOperation['files'] ?? $trashOperation;
    $unresolved = [];

    for ($i = count($movedFiles) - 1; $i >= 0; $i--) {
        $rawEntry = (array) $movedFiles[$i];
        $file = resolve_trash_manifest_entry($rawEntry);

        if ($file === null || $file['from'] === null || $file['trash'] === null) {
            $errors[] = 'Некоректний запис у журналі кошика.';
            $unresolved[] = $rawEntry;
            continue;
        }

        if (is_file($file['trash']) && !rename($file['trash'], $file['from'])) {
            $errors[] = 'Не вдалося повернути файл ' . basename((string) $file['from']) . '.';
            $unresolved[] = $rawEntry;
        }
    }

    if (isset($trashOperation['manifest']) && is_file((string) $trashOperation['manifest'])) {
        $manifestPath = (string) $trashOperation['manifest'];
        if ($errors === []) {
            if (!@unlink($manifestPath)) {
                $errors[] = 'Не вдалося видалити завершений журнал rollback.';
            }
        } elseif (!rewrite_trash_manifest_unresolved($manifestPath, $unresolved, 'rollback_partial')) {
            $errors[] = 'Не вдалося оновити журнал часткового rollback.';
        }
    }

    return $errors;
}

function remove_trashed_photo_files(array $trashOperation): array
{
    $errors = [];
    $movedFiles = $trashOperation['files'] ?? $trashOperation;
    $unresolved = [];

    foreach ($movedFiles as $rawEntry) {
        $rawEntry = (array) $rawEntry;
        $file = resolve_trash_manifest_entry($rawEntry);

        if ($file === null || $file['trash'] === null) {
            $errors[] = 'Некоректний запис у журналі кошика.';
            $unresolved[] = $rawEntry;
            continue;
        }

        if (is_file($file['trash']) && !@unlink($file['trash'])) {
            $errors[] = 'Не вдалося остаточно видалити файл ' . basename((string) $file['trash']) . '.';
            $unresolved[] = $rawEntry;
        }
    }

    if (isset($trashOperation['manifest']) && is_file((string) $trashOperation['manifest'])) {
        $manifestPath = (string) $trashOperation['manifest'];
        if ($errors === []) {
            if (!@unlink($manifestPath)) {
                $errors[] = 'Не вдалося видалити журнал операції кошика.';
            }
        } elseif (!rewrite_trash_manifest_unresolved($manifestPath, $unresolved, 'purge_partial')) {
            $errors[] = 'Не вдалося оновити журнал часткового purge.';
        }
    }

    return $errors;
}
