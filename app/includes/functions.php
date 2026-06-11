<?php

declare(strict_types=1);

function project_root_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);
    $path = trim($path, DIRECTORY_SEPARATOR);

    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . $path;
}

function public_path(string $path = ''): string
{
    $public = project_root_path('public');
    $path = trim($path, DIRECTORY_SEPARATOR);

    return $path === '' ? $public : $public . DIRECTORY_SEPARATOR . $path;
}

function storage_path(string $path = ''): string
{
    $storage = project_root_path('storage');
    $path = trim($path, DIRECTORY_SEPARATOR);

    return $path === '' ? $storage : $storage . DIRECTORY_SEPARATOR . $path;
}

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require project_root_path('config' . DIRECTORY_SEPARATOR . 'config.php');
    }

    return $config;
}

function db_config(): array
{
    $path = project_root_path('config' . DIRECTORY_SEPARATOR . 'database.php');

    if (!file_exists($path)) {
        throw new RuntimeException('Файл config/database.php не знайдено. Скопіюйте config/database.example.php і впишіть налаштування бази даних.');
    }

    return require $path;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = db_config();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            (int) $config['DB_PORT'],
            $config['DB_NAME']
        );

        $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

function app_name(): string
{
    return (string) app_config()['APP_NAME'];
}

function url(string $path = ''): string
{
    $basePath = (string) (parse_url((string) app_config()['APP_URL'], PHP_URL_PATH) ?: '');
    $basePath = '/' . trim($basePath, '/');
    $basePath = $basePath === '/' ? '' : $basePath;
    $path = ltrim($path, '/');

    return $path === '' ? $basePath . '/' : $basePath . '/' . $path;
}

function local_url(string $path = ''): string
{
    return url($path);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    send_security_headers();
    header('Location: ' . url($path));
    exit;
}

function app_debug(): bool
{
    return (bool) (app_config()['APP_DEBUG'] ?? false);
}

function configure_runtime(): void
{
    $displayErrors = app_debug() ? '1' : '0';
    $logDir = storage_path('logs');

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    ini_set('display_errors', $displayErrors);
    ini_set('display_startup_errors', $displayErrors);
    ini_set('log_errors', '1');
    ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'php-error.log');
}

function app_log(string $message): void
{
    $logDir = storage_path('logs');

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'app.log';

    if (!@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX)) {
        error_log($message);
    }
}

function app_log_exception(Throwable $exception, string $context): void
{
    app_log($context . ': ' . $exception::class . ' - ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
}

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function session_cookie_options(): array
{
    $config = app_config();

    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request() || (($config['APP_ENV'] ?? '') === 'production' && str_starts_with((string) $config['APP_URL'], 'https://')),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(session_cookie_options());
        session_start();
    }
}

function has_session_cookie(): bool
{
    return isset($_COOKIE[session_name()]);
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
}

function send_admin_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function set_flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE && !has_session_cookie()) {
        return [];
    }

    start_session();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function get_int(string $key): ?int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

    return $value === false ? null : $value;
}

function uploads_path(string $folder, string $filename = ''): string
{
    $path = public_path('uploads' . DIRECTORY_SEPARATOR . $folder);

    return $filename === '' ? $path : $path . DIRECTORY_SEPARATOR . $filename;
}

function uploads_url(string $folder, string $filename): string
{
    return url('uploads/' . $folder . '/' . rawurlencode($filename));
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
    return preg_match('/\A[a-f0-9]{32}\.jpg\z/', $filename) === 1;
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

function ensure_upload_folders(): array
{
    $errors = [];
    $folders = [
        originals_path(),
        trash_path(),
        uploads_path('large'),
        uploads_path('thumbnails'),
    ];

    foreach ($folders as $folder) {
        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            $errors[] = 'Не вдалося створити папку для завантажень.';
            continue;
        }

        if (!is_writable($folder)) {
            $errors[] = 'Папка для завантажень недоступна для запису.';
        }
    }

    return $errors;
}

function safe_original_name(string $name): string
{
    $name = basename($name);

    return text_limit($name, 255);
}

function text_limit(string $text, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length);
    }

    return substr($text, 0, $length);
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
    $largePixels = min($width, (int) app_config()['LARGE_MAX_WIDTH']) * $height;
    $largeImage = $largePixels * $bytesPerPixel;
    $thumbnailPixels = min($width, 600) * $height;
    $thumbnailImage = $thumbnailPixels * $bytesPerPixel;
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
        imageflip($image, IMG_FLIP_HORIZONTAL);
        return $image;
    }

    if ($orientation === 3) {
        $rotated = imagerotate($image, 180, 0);
        return $rotated ?: $image;
    }

    if ($orientation === 4) {
        imageflip($image, IMG_FLIP_VERTICAL);
        return $image;
    }

    if ($orientation === 5) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
        $rotated = imagerotate($image, 90, 0);
        return $rotated ?: $image;
    }

    if ($orientation === 6) {
        $rotated = imagerotate($image, -90, 0);
        return $rotated ?: $image;
    }

    if ($orientation === 7) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
        $rotated = imagerotate($image, -90, 0);
        return $rotated ?: $image;
    }

    if ($orientation === 8) {
        $rotated = imagerotate($image, 90, 0);
        return $rotated ?: $image;
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
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    if (!imagejpeg($resized, $destination, $quality)) {
        imagedestroy($resized);
        imagedestroy($image);
        throw new RuntimeException('Не вдалося зберегти зменшену копію.');
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
    imagecopyresampled($resized, $oriented, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    if (!imagejpeg($resized, $destination, $quality)) {
        imagedestroy($resized);
        if ($oriented !== $image) {
            imagedestroy($oriented);
        }

        imagedestroy($image);
        throw new RuntimeException('Не вдалося зберегти зменшену копію.');
    }

    imagedestroy($resized);
    if ($oriented !== $image) {
        imagedestroy($oriented);
    }

    imagedestroy($image);
}

function create_thumbnail(string $source, string $destination, mixed $orientation, int $maxWidth = 600): void
{
    create_oriented_resized_jpeg($source, $destination, $maxWidth, 85, $orientation);
}

function create_large_image(string $source, string $destination, mixed $orientation): void
{
    create_oriented_resized_jpeg($source, $destination, (int) app_config()['LARGE_MAX_WIDTH'], 86, $orientation);
}

function photo_display_url(array $photo): string
{
    $filename = (string) $photo['filename'];

    if (safe_existing_upload_file_path('large', $filename) !== null) {
        return uploads_url('large', $filename);
    }

    return uploads_url('thumbnails', (string) $photo['thumbnail_filename']);
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
    }

    $thumbnailPath = safe_existing_upload_file_path('thumbnails', $thumbnail);
    if ($thumbnailPath !== null) {
        $paths[] = $thumbnailPath;
    }

    return $paths;
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

function validate_photo_files_deletable(array $photo): array
{
    $errors = photo_filename_errors($photo);

    foreach (photo_file_paths($photo) as $file) {
        if (is_file($file) && !is_writable($file)) {
            $errors[] = 'Немає права видалити файл ' . basename($file) . '.';
        }
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
    $moved = [];

    foreach (photo_file_paths($photo) as $file) {
        $trashName = bin2hex(random_bytes(8)) . '-' . basename($file);
        $trashFile = trash_path($trashName);

        if (!rename($file, $trashFile)) {
            restore_moved_photo_files($moved);
            throw new RuntimeException('Не вдалося перемістити файл у кошик: ' . basename($file));
        }

        $moved[] = ['from' => $file, 'trash' => $trashFile];
    }

    return $moved;
}

function restore_moved_photo_files(array $movedFiles): array
{
    $errors = [];

    for ($i = count($movedFiles) - 1; $i >= 0; $i--) {
        $file = $movedFiles[$i];

        if (is_file($file['trash']) && !rename($file['trash'], $file['from'])) {
            $errors[] = 'Не вдалося повернути файл ' . basename((string) $file['from']) . '.';
        }
    }

    return $errors;
}

function remove_trashed_photo_files(array $movedFiles): array
{
    $errors = [];

    foreach ($movedFiles as $file) {
        if (is_file($file['trash']) && !@unlink($file['trash'])) {
            $errors[] = 'Не вдалося остаточно видалити файл ' . basename((string) $file['trash']) . '.';
        }
    }

    return $errors;
}

configure_runtime();
