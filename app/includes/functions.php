<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/maintenance_functions.php';
require_once __DIR__ . '/file_functions.php';
require_once __DIR__ . '/gallery_functions.php';
require_once __DIR__ . '/share_functions.php';
require_once __DIR__ . '/media_access_functions.php';
require_once __DIR__ . '/album_zip_functions.php';
require_once __DIR__ . '/tag_service.php';

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

function app_env(): string
{
    return (string) (app_config()['APP_ENV'] ?? 'local');
}

function is_production(): bool
{
    return app_env() === 'production';
}

function app_http_error(string $message, int $statusCode = 500, ?Throwable $exception = null): never
{
    if ($exception !== null) {
        app_log_exception($exception, 'HTTP ' . $statusCode);
    } else {
        app_log('HTTP ' . $statusCode . ': ' . $message);
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        send_security_headers();
        header('Content-Type: text/html; charset=UTF-8');
    }

    $safeMessage = app_debug() && $exception !== null
        ? $message . ' ' . $exception->getMessage()
        : $message;

    $errorTitles = [
        400 => 'Некоректний запит',
        401 => 'Потрібна авторизація',
        403 => 'Доступ заборонено',
        404 => 'Сторінку не знайдено',
        409 => 'Конфлікт даних',
        410 => 'Ресурс недоступний',
        413 => 'Запит завеликий',
        429 => 'Занадто багато запитів',
        500 => 'Помилка сервера',
        503 => 'Сервіс тимчасово недоступний',
    ];
    $errorStatusCode = $statusCode;
    $errorTitle = $errorTitles[$statusCode] ?? ($statusCode >= 500 ? 'Помилка сервера' : 'Помилка запиту');
    $errorMessage = $safeMessage;
    $errorDetails = app_debug() && $exception !== null ? $exception->getTraceAsString() : '';
    $errorPage = public_path($statusCode >= 500 ? '500.php' : '404.php');

    if (is_file($errorPage)) {
        require $errorPage;
        exit;
    }

    echo '<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Помилка</title></head><body><h1>' . h($errorTitle) . '</h1><p>' . h($safeMessage) . '</p></body></html>';
    exit;
}

function app_url_is_https(): bool
{
    return str_starts_with((string) app_config()['APP_URL'], 'https://');
}

function validate_runtime_config(): void
{
    if (!is_production()) {
        return;
    }

    if (app_debug()) {
        app_http_error('Небезпечна конфігурація: APP_DEBUG має бути false у production.', 500);
    }

    if (!app_url_is_https()) {
        app_http_error('Небезпечна конфігурація: APP_URL має починатися з https:// у production.', 500);
    }
}

function runtime_extension_profile(): string
{
    if (defined('MYGALLERY_RUNTIME_PROFILE')) {
        return (string) constant('MYGALLERY_RUNTIME_PROFILE');
    }

    if (PHP_SAPI === 'cli') {
        $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        return match ($script) {
            'cleanup_runtime.php' => 'maintenance',
            'migrate.php', 'setup.php' => 'database',
            'backup.php', 'restore.php', 'verify_backup.php' => 'database-archive',
            'build_release.php' => 'archive',
            default => 'full',
        };
    }

    return 'full';
}

function required_php_extensions(?string $profile = null): array
{
    $profile ??= runtime_extension_profile();

    return match ($profile) {
        'maintenance' => ['fileinfo'],
        'database' => ['pdo', 'pdo_mysql'],
        'archive' => ['zip'],
        'database-archive' => ['pdo', 'pdo_mysql', 'zip'],
        default => ['pdo', 'pdo_mysql', 'gd', 'fileinfo', 'exif', 'mbstring', 'zip'],
    };
}

function missing_php_extensions(?string $profile = null): array
{
    return array_values(array_filter(
        required_php_extensions($profile),
        static fn (string $extension): bool => !extension_loaded($extension)
            || ($extension === 'zip' && !class_exists('ZipArchive'))
    ));
}

function ensure_required_php_extensions(?string $profile = null): void
{
    $missing = missing_php_extensions($profile);

    if (!empty($missing)) {
        app_http_error('Відсутні обов’язкові PHP-розширення для профілю '
            . ($profile ?? runtime_extension_profile()) . ': ' . implode(', ', $missing) . '.', 500);
    }
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

function absolute_url(string $path = ''): string
{
    $appUrl = rtrim((string) app_config()['APP_URL'], '/');
    $parts = parse_url($appUrl);
    if (!is_array($parts)
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || !is_string($parts['host'] ?? null)
        || $parts['host'] === ''
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])) {
        throw new RuntimeException('APP_URL не містить валідний HTTP(S) origin.');
    }
    $path = ltrim($path, '/');

    return $path === '' ? $appUrl . '/' : $appUrl . '/' . $path;
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

function redirect_to(string $path, array $params = []): never
{
    send_security_headers();
    $url = url($path);
    if (!empty($params)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

function app_debug(): bool
{
    return (bool) (app_config()['APP_DEBUG'] ?? false);
}

function rotate_runtime_log_unlocked(string $logFile, int $maxSize, int $retention): bool
{
    if (is_link($logFile)) {
        return false;
    }
    if (!is_file($logFile)) {
        return true;
    }

    $size = filesize($logFile);
    if (!is_int($size)) {
        return false;
    }
    if ($size <= $maxSize) {
        return enforce_private_file_permissions($logFile);
    }

    for ($index = $retention; $index >= 1; $index--) {
        $source = $index === 1 ? $logFile : $logFile . '.' . ($index - 1);
        $target = $logFile . '.' . $index;
        if (!is_file($source)) {
            continue;
        }
        if (is_link($source) || is_link($target)) {
            return false;
        }
        if ($index === $retention && is_file($target)) {
            if (!@unlink($target)) {
                return false;
            }
        }
        if (!@rename($source, $target)) {
            return false;
        }
        if (!enforce_private_file_permissions($target)) {
            return false;
        }
    }

    return true;
}

function with_runtime_log_lock(string $logFile, callable $callback): mixed
{
    $lock = open_private_file($logFile . '.rotation.lock', 'c+b');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            fclose($lock);
        }

        return false;
    }

    try {
        return $callback();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function rotate_runtime_log(string $logFile, int $maxSize = 5242880, int $retention = 3): bool
{
    return with_runtime_log_lock(
        $logFile,
        static function () use ($logFile, $maxSize, $retention): bool {
            return rotate_runtime_log_unlocked($logFile, $maxSize, $retention);
        }
    ) === true;
}

function append_rotating_private_log(
    string $logFile,
    string $line,
    int $maxSize = 5242880,
    int $retention = 3
): bool {
    if ($maxSize < 1 || $retention < 1 || is_link($logFile) || !ensure_private_directory(dirname($logFile))) {
        return false;
    }

    return with_runtime_log_lock(
        $logFile,
        static function () use ($logFile, $line, $maxSize, $retention): bool {
            if (!rotate_runtime_log_unlocked($logFile, $maxSize, $retention)) {
                return false;
            }

            return private_file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === strlen($line);
        }
    ) === true;
}

function configure_runtime(): void
{
    $displayErrors = app_debug() ? '1' : '0';
    $logDir = storage_path('logs');

    if (!ensure_private_directory($logDir)) {
        app_http_error('Папка журналів недоступна для запису.', 500);
    }

    $phpErrorLog = $logDir . DIRECTORY_SEPARATOR . 'php-error.log';
    $logPrepared = with_runtime_log_lock(
        $phpErrorLog,
        static function () use ($phpErrorLog): bool {
            if (!rotate_runtime_log_unlocked($phpErrorLog, 5242880, 3)) {
                return false;
            }

            return is_file($phpErrorLog) || private_file_put_contents($phpErrorLog, '') !== false;
        }
    );
    if ($logPrepared !== true) {
        app_http_error('Не вдалося безпечно підготувати журнал PHP.', 500);
    }

    ini_set('display_errors', $displayErrors);
    ini_set('display_startup_errors', $displayErrors);
    ini_set('log_errors', '1');
    ini_set('error_log', $phpErrorLog);

    validate_runtime_config();
    ensure_required_php_extensions();
}

function app_log(string $message): void
{
    $logDir = storage_path('logs');

    if (!ensure_private_directory($logDir)) {
        error_log($message);
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'app.log';
    $written = append_rotating_private_log($logFile, $line);

    if (!$written) {
        error_log($message);
    }
}

function app_log_exception(Throwable $exception, string $context): void
{
    app_log($context . ': ' . $exception::class . ' - ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
}

function direct_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function canonical_ip_address(string $address): ?string
{
    $packed = inet_pton(trim($address));
    if ($packed === false) {
        return null;
    }

    $canonical = inet_ntop($packed);

    return is_string($canonical) && $canonical !== '' ? strtolower($canonical) : null;
}

/** @return list<string> */
function canonical_ip_list(mixed $addresses): array
{
    if (!is_array($addresses)) {
        return [];
    }

    $canonical = [];
    foreach ($addresses as $address) {
        if (!is_string($address)) {
            continue;
        }
        $normalized = canonical_ip_address($address);
        if ($normalized !== null) {
            $canonical[$normalized] = true;
        }
    }

    return array_keys($canonical);
}

function request_from_trusted_proxy(): bool
{
    $remoteAddr = canonical_ip_address(is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '');
    $trustedProxies = canonical_ip_list(app_config()['TRUSTED_PROXIES'] ?? []);

    return $remoteAddr !== null
        && in_array($remoteAddr, $trustedProxies, true);
}

function client_ip(?array $server = null, ?array $trustedProxies = null, string $default = 'unknown'): string
{
    $server ??= $_SERVER;
    $remoteValue = $server['REMOTE_ADDR'] ?? $default;
    $remoteAddr = is_string($remoteValue) ? canonical_ip_address($remoteValue) : null;
    $remoteAddr ??= $default;

    if ($trustedProxies === null) {
        $trustedProxies = app_config()['TRUSTED_PROXIES'] ?? [];
    }
    $trustedProxies = canonical_ip_list($trustedProxies);

    if (in_array($remoteAddr, $trustedProxies, true)) {
        $forwardedFor = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        $candidates = [];
        foreach (explode(',', $forwardedFor) as $candidate) {
            $normalized = canonical_ip_address($candidate);
            if ($normalized !== null) {
                $candidates[] = $normalized;
            }
        }

        // Walk from the proxy closest to us toward the client. Trusted hops are
        // discarded; the first untrusted address is the effective client.
        for ($index = count($candidates) - 1; $index >= 0; $index--) {
            if (!in_array($candidates[$index], $trustedProxies, true)) {
                return $candidates[$index];
            }
        }
    }

    return $remoteAddr;
}

function forwarded_proto_is_https(): bool
{
    $forwardedProto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $proto = strtolower(trim(explode(',', $forwardedProto)[0] ?? ''));

    return $proto === 'https';
}

function trusted_forwarded_https_request(): bool
{
    return request_from_trusted_proxy() && forwarded_proto_is_https();
}

function is_https_request(): bool
{
    return direct_https_request() || trusted_forwarded_https_request();
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

function request_raw_string(array $source, string $key, string $default = '', int $maxLength = 4096): string
{
    $value = $source[$key] ?? $default;
    if (!is_string($value)) {
        return $default;
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function request_string(array $source, string $key, int $maxLength = 255, string $default = ''): string
{
    $value = $source[$key] ?? $default;
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return $default;
    }

    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';

    return text_limit(trim($value), $maxLength);
}

function request_int(array $source, string $key, ?int $default = null, ?int $minimum = null, ?int $maximum = null): ?int
{
    $value = $source[$key] ?? null;
    if (!is_string($value) && !is_int($value)) {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if (!is_int($parsed)) {
        return $default;
    }
    if ($minimum !== null && $parsed < $minimum) {
        return $default;
    }
    if ($maximum !== null && $parsed > $maximum) {
        return $default;
    }

    return $parsed;
}

function request_string_list(array $source, string $key, int $maximumItems = 500): array
{
    $value = $source[$key] ?? [];
    if (!is_array($value) || count($value) > $maximumItems) {
        return [];
    }

    return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) || is_int($item)));
}

function get_int(string $key): ?int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value === null) {
        $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    }

    return $value === false ? null : $value;
}

function get_query_string(string $key, int $maxLength = 255): string
{
    $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);

    if (!is_string($value)) {
        return '';
    }

    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

    return text_limit(trim($value), $maxLength);
}

function is_valid_date_string(string $date): bool
{
    if ($date === '') {
        return false;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsed instanceof DateTimeImmutable
        && $parsed->format('Y-m-d') === $date
        && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0));
}

function normalize_date_query(string $date): string
{
    return is_valid_date_string($date) ? $date : '';
}

function clean_description(string $description): ?string
{
    $description = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $description) ?? '');

    if ($description === '') {
        return null;
    }

    return text_limit($description, description_max_length());
}

function description_max_length(): int
{
    return 10000;
}

function pagination_window(int $page, int $totalPages, int $radius = 2): array
{
    if ($totalPages <= 9) {
        return range(1, max(1, $totalPages));
    }

    $pages = [1, $totalPages];

    for ($i = max(2, $page - $radius); $i <= min($totalPages - 1, $page + $radius); $i++) {
        $pages[] = $i;
    }

    $pages = array_values(array_unique($pages));
    sort($pages);
    $window = [];
    $previous = null;

    foreach ($pages as $item) {
        if ($previous !== null && $item > $previous + 1) {
            $window[] = null;
        }

        $window[] = $item;
        $previous = $item;
    }

    return $window;
}

function url_with_query(string $path, array $params = []): string
{
    $cleanParams = [];

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $cleanParams[$key] = $value;
    }

    if (empty($cleanParams)) {
        return url($path);
    }

    return url($path . '?' . http_build_query($cleanParams));
}

function clean_album_name(string $name): string
{
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

    return text_limit(trim($name), 100);
}

function get_album_id_from_request(string $key = 'album_id'): ?int
{
    $id = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

    return $id === false || $id === null || $id < 1 ? null : $id;
}

function get_album_id_from_post(string $key = 'album_id'): ?int
{
    $raw = $_POST[$key] ?? null;

    if ($raw === null || $raw === '') {
        return null;
    }
    if (!is_string($raw) && !is_int($raw)) {
        throw new InvalidArgumentException('Некоректний альбом.');
    }

    $id = filter_var($raw, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Некоректний альбом.');
    }

    return (int) $id;
}

function get_album_options(bool $withCounts = false, bool $includePrivate = false): array
{
    $where = $includePrivate ? '' : ' WHERE albums.is_private = 0 ';
    if ($withCounts) {
        $stmt = db()->query(
            "SELECT albums.id, albums.name, albums.cover_photo_id, albums.sort_order, albums.is_private, COUNT(photos.id) AS photo_count
            FROM albums
            LEFT JOIN photos ON photos.album_id = albums.id
            $where
            GROUP BY albums.id, albums.name, albums.cover_photo_id, albums.sort_order, albums.is_private
            ORDER BY albums.sort_order ASC, albums.name ASC"
        );

        return $stmt->fetchAll();
    }

    $stmt = db()->query("SELECT id, name, cover_photo_id, sort_order, is_private FROM albums $where ORDER BY sort_order ASC, name ASC");

    return $stmt->fetchAll();
}

function get_public_albums_with_covers(bool $includePrivate = false): array
{
    $where = $includePrivate ? '' : ' WHERE a.is_private = 0 ';
    $stmt = db()->query(
        "SELECT
            a.id,
            a.name,
            a.cover_photo_id,
            a.sort_order,
            a.is_private,
            p.id AS photo_id,
            p.filename,
            p.thumbnail_filename,
            p.width,
            p.title AS cover_title,
            p.dominant_color,
            COUNT(p2.id) AS photo_count,
            MAX(p2.created_at) AS last_photo_at
        FROM albums a
        LEFT JOIN photos p ON p.id = COALESCE(
            (
                SELECT p_cover.id
                FROM photos p_cover
                WHERE p_cover.id = a.cover_photo_id
                  AND p_cover.album_id = a.id
                LIMIT 1
            ),
            (
                SELECT p3.id
                FROM photos p3
                WHERE p3.album_id = a.id
                ORDER BY p3.created_at DESC, p3.id DESC
                LIMIT 1
            )
        )
        LEFT JOIN photos p2 ON p2.album_id = a.id
        $where
        GROUP BY a.id, a.name, a.cover_photo_id, a.sort_order, a.is_private, p.id, p.filename, p.thumbnail_filename, p.width, p.title, p.dominant_color
        ORDER BY a.sort_order ASC, a.name ASC"
    );

    return $stmt->fetchAll();
}

function invalid_album_cover_count(): int
{
    $stmt = db()->query(
        'SELECT COUNT(*)
        FROM albums
        INNER JOIN photos ON photos.id = albums.cover_photo_id
        WHERE albums.cover_photo_id IS NOT NULL
          AND (photos.album_id IS NULL OR photos.album_id <> albums.id)'
    );

    return (int) $stmt->fetchColumn();
}

function album_exists(int $id, ?PDO $pdo = null): bool
{
    $pdo ??= db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM albums WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}

function find_or_create_album(string $name, int $isPrivate = 0, ?PDO $pdo = null): int
{
    $name = clean_album_name($name);

    if ($name === '') {
        throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
    }

    $pdo ??= db();
    $stmt = $pdo->prepare(
        'INSERT INTO albums (name, is_private) VALUES (:name, :is_private)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $stmt->execute(['name' => $name, 'is_private' => $isPrivate]);

    return (int) $pdo->lastInsertId();
}

function create_album_strict(string $name, int $isPrivate = 0): int
{
    $name = clean_album_name($name);
    if ($name === '') {
        throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
    }

    $existing = db()->prepare('SELECT id FROM albums WHERE name = ? LIMIT 1');
    $existing->execute([$name]);
    if ($existing->fetchColumn() !== false) {
        throw new InvalidArgumentException('Альбом із такою назвою вже існує; його приватність не змінено.');
    }

    try {
        $stmt = db()->prepare('INSERT INTO albums (name, is_private) VALUES (?, ?)');
        $stmt->execute([$name, $isPrivate === 1 ? 1 : 0]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            throw new InvalidArgumentException('Альбом із такою назвою вже існує; його приватність не змінено.', 0, $exception);
        }
        throw $exception;
    }

    return (int) db()->lastInsertId();
}

function resolve_album_id_from_post(): ?int
{
    $albumId = get_album_id_from_post('album_id');
    $newAlbumName = clean_album_name(request_string($_POST, 'new_album_name', 100));

    if ($newAlbumName !== '') {
        return find_or_create_album($newAlbumName);
    }

    if ($albumId !== null && !album_exists($albumId)) {
        throw new InvalidArgumentException('Обраний альбом не знайдено.');
    }

    return $albumId;
}

function tag_name_max_length(): int
{
    return 60;
}

function clean_tag_name(string $name): string
{
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

    return text_limit($name, tag_name_max_length());
}

function tag_slug(string $name): string
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $base = preg_replace('/[^\p{L}\p{N}]+/u', '-', $normalized) ?? '';
    $base = trim($base, '-');

    if ($base === '') {
        $base = 'tag';
    }

    $base = text_limit($base, 72);

    return $base . '-' . substr(sha1($normalized), 0, 8);
}

function parse_tags_input(string $input): array
{
    $input = str_replace(["\r\n", "\r", "\n", ';'], ',', $input);
    $parts = array_filter(array_map('clean_tag_name', explode(',', $input)), static fn (string $tag): bool => $tag !== '');
    $tags = [];
    $seen = [];

    foreach ($parts as $tag) {
        $key = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $tags[] = $tag;
    }

    if (count($tags) > 20) {
        throw new InvalidArgumentException('Максимум 20 тегів для однієї фотографії.');
    }

    return $tags;
}

function tags_for_input(array $tags): string
{
    $names = array_map(static fn (array $tag): string => (string) $tag['name'], $tags);

    return implode(', ', $names);
}

function get_tag_id_from_request(string $key = 'tag_id'): ?int
{
    $id = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

    return $id === false || $id === null || $id < 1 ? null : $id;
}

function get_tag_options(bool $withCounts = false, bool $includePrivate = true): array
{
    if ($withCounts) {
        if (!$includePrivate) {
            $stmt = db()->query(
                'SELECT tags.id, tags.name, tags.slug, COUNT(DISTINCT photo_tags.photo_id) AS photo_count
                FROM tags
                INNER JOIN photo_tags ON photo_tags.tag_id = tags.id
                INNER JOIN photos ON photos.id = photo_tags.photo_id
                LEFT JOIN albums ON albums.id = photos.album_id
                WHERE albums.is_private IS NULL OR albums.is_private = 0
                GROUP BY tags.id, tags.name, tags.slug
                ORDER BY tags.name ASC'
            );

            return $stmt->fetchAll();
        }

        $stmt = db()->query(
            'SELECT tags.id, tags.name, tags.slug, COUNT(photo_tags.photo_id) AS photo_count
            FROM tags
            LEFT JOIN photo_tags ON photo_tags.tag_id = tags.id
            GROUP BY tags.id, tags.name, tags.slug
            ORDER BY tags.name ASC'
        );

        return $stmt->fetchAll();
    }

    if (!$includePrivate) {
        $stmt = db()->query(
            'SELECT DISTINCT tags.id, tags.name, tags.slug
            FROM tags
            INNER JOIN photo_tags ON photo_tags.tag_id = tags.id
            INNER JOIN photos ON photos.id = photo_tags.photo_id
            LEFT JOIN albums ON albums.id = photos.album_id
            WHERE albums.is_private IS NULL OR albums.is_private = 0
            ORDER BY tags.name ASC'
        );

        return $stmt->fetchAll();
    }

    $stmt = db()->query('SELECT id, name, slug FROM tags ORDER BY name ASC');

    return $stmt->fetchAll();
}

function tag_exists(int $id): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM tags WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}

function find_or_create_tag(string $name, ?PDO $pdo = null): int
{
    $name = clean_tag_name($name);

    if ($name === '') {
        throw new InvalidArgumentException('Назва тегу не може бути порожньою.');
    }

    $pdo ??= db();
    $stmt = $pdo->prepare(
        'INSERT INTO tags (name, slug) VALUES (:name, :slug)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)'
    );
    $stmt->execute([
        'name' => $name,
        'slug' => tag_slug($name),
    ]);

    return (int) $pdo->lastInsertId();
}

function sync_photo_tags(int $photoId, array $tagNames, ?PDO $pdo = null): void
{
    $pdo ??= db();
    $deleteStmt = $pdo->prepare('DELETE FROM photo_tags WHERE photo_id = :photo_id');
    $deleteStmt->execute(['photo_id' => $photoId]);

    if (empty($tagNames)) {
        return;
    }

    $insertStmt = $pdo->prepare('INSERT IGNORE INTO photo_tags (photo_id, tag_id) VALUES (:photo_id, :tag_id)');

    foreach ($tagNames as $tagName) {
        $tagId = find_or_create_tag($tagName, $pdo);
        $insertStmt->execute([
            'photo_id' => $photoId,
            'tag_id' => $tagId,
        ]);
    }
}

function get_photo_tags(int $photoId): array
{
    $stmt = db()->prepare(
        'SELECT tags.id, tags.name, tags.slug
        FROM tags
        INNER JOIN photo_tags ON photo_tags.tag_id = tags.id
        WHERE photo_tags.photo_id = :photo_id
        ORDER BY tags.name ASC'
    );
    $stmt->execute(['photo_id' => $photoId]);

    return $stmt->fetchAll();
}

function get_photo_tags_map(array $photoIds): array
{
    $photoIds = array_values(array_unique(array_filter(array_map('intval', $photoIds), static fn (int $id): bool => $id > 0)));

    if (empty($photoIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = db()->prepare(
        'SELECT photo_tags.photo_id, tags.id, tags.name, tags.slug
        FROM photo_tags
        INNER JOIN tags ON tags.id = photo_tags.tag_id
        WHERE photo_tags.photo_id IN (' . $placeholders . ')
        ORDER BY tags.name ASC'
    );
    $stmt->execute($photoIds);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $photoId = (int) $row['photo_id'];
        unset($row['photo_id']);
        $map[$photoId][] = $row;
    }

    return $map;
}

function prune_unused_tags(): void
{
    db()->exec('DELETE tags FROM tags LEFT JOIN photo_tags ON photo_tags.tag_id = tags.id WHERE photo_tags.tag_id IS NULL');
}

function text_limit(string $text, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length);
    }

    return substr($text, 0, $length);
}

function normalize_gallery_filters(array $input): array
{
    $dateFrom = normalize_date_query(request_string($input, 'date_from', 10));
    $dateTo = normalize_date_query(request_string($input, 'date_to', 10));

    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $sort = request_string($input, 'sort', 32, 'newest');
    $sortOptions = ['newest', 'oldest', 'taken_newest', 'taken_oldest', 'title_az', 'title_za'];
    if (!in_array($sort, $sortOptions, true)) {
        $sort = 'newest';
    }

    return [
        'q' => request_string($input, 'q', 120),
        'camera' => request_string($input, 'camera', 150),
        'album_id' => request_int($input, 'album_id', null, 1),
        'tag_id' => request_int($input, 'tag_id', null, 1),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'page' => request_int($input, 'page', 1, 1) ?? 1,
    ];
}

function build_gallery_where_clause(array $filters, array &$params, bool $includeOriginalName = false, bool $includePrivate = false): string
{
    $where = [];
    $joinSql = '';

    $searchCondition = photo_search_condition($filters['q'], $includeOriginalName, $params);
    if ($searchCondition !== '') {
        $where[] = $searchCondition;
    }

    if ($filters['camera'] !== '') {
        $where[] = 'photos.camera_model = :camera';
        $params['camera'] = $filters['camera'];
    }

    if ($filters['album_id'] !== null) {
        $where[] = 'photos.album_id = :album_id';
        $params['album_id'] = $filters['album_id'];
    }

    if ($filters['tag_id'] !== null) {
        $joinSql = ' INNER JOIN photo_tags photo_tags_filter ON photo_tags_filter.photo_id = photos.id AND photo_tags_filter.tag_id = :tag_id';
        $params['tag_id'] = $filters['tag_id'];
    }

    if ($filters['date_from'] !== '') {
        $where[] = 'photos.taken_at >= :date_from';
        $params['date_from'] = $filters['date_from'] . ' 00:00:00';
    }

    if ($filters['date_to'] !== '') {
        $where[] = 'photos.taken_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }

    // Exclude private albums unless the caller explicitly requests a wider admin/share view.
    // The privacy decision is passed as an argument, never read from global state.
    if (!$includePrivate) {
        $where[] = '(albums.is_private IS NULL OR albums.is_private = 0)';
    }

    $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

    return $joinSql . $whereSql;
}

function count_photos(PDO $pdo, array $filters, bool $includeOriginalName = false, bool $includePrivate = false): int
{
    $params = [];
    $sqlSuffix = build_gallery_where_clause($filters, $params, $includeOriginalName, $includePrivate);

    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT photos.id) FROM photos LEFT JOIN albums ON albums.id = photos.album_id' . $sqlSuffix);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function fetch_photos(PDO $pdo, array $filters, int $limit, int $offset, bool $includeOriginalName = false, bool $includePrivate = false): array
{
    $sortSql = [
        'newest' => 'photos.created_at DESC, photos.id DESC',
        'oldest' => 'photos.created_at ASC, photos.id ASC',
        'taken_newest' => 'photos.taken_at IS NULL ASC, photos.taken_at DESC, photos.created_at DESC, photos.id DESC',
        'taken_oldest' => 'photos.taken_at IS NULL ASC, photos.taken_at ASC, photos.created_at ASC, photos.id ASC',
        'title_az' => 'photos.title ASC, photos.id ASC',
        'title_za' => 'photos.title DESC, photos.id DESC',
    ];

    $sort = array_key_exists($filters['sort'], $sortSql) ? $filters['sort'] : 'newest';

    $params = [];
    $sqlSuffix = build_gallery_where_clause($filters, $params, $includeOriginalName, $includePrivate);

    $selectCols = 'photos.id, photos.filename, photos.thumbnail_filename, photos.width, photos.title, photos.camera_model, photos.taken_at, photos.dominant_color, albums.name AS album_name';
    if ($includeOriginalName) {
        $selectCols .= ', photos.original_name, photos.description, photos.created_at, photos.file_size';
    }

    $stmt = $pdo->prepare(
        "SELECT $selectCols
        FROM photos
        LEFT JOIN albums ON albums.id = photos.album_id" . $sqlSuffix . '
        ORDER BY ' . $sortSql[$sort] . '
        LIMIT :limit OFFSET :offset'
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function fetch_photo_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT photos.*, albums.name AS album_name, albums.is_private AS album_is_private
        FROM photos
        LEFT JOIN albums ON albums.id = photos.album_id
        WHERE photos.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $photo = $stmt->fetch();

    return $photo ?: null;
}

function fetch_filter_options(PDO $pdo, bool $includePrivate = false): array
{
    $cameraSql = $includePrivate
        ? "SELECT DISTINCT camera_model FROM photos WHERE camera_model IS NOT NULL AND camera_model <> '' ORDER BY camera_model ASC"
        : "SELECT DISTINCT photos.camera_model
            FROM photos
            LEFT JOIN albums ON albums.id = photos.album_id
            WHERE photos.camera_model IS NOT NULL
              AND photos.camera_model <> ''
              AND (albums.is_private IS NULL OR albums.is_private = 0)
            ORDER BY photos.camera_model ASC";

    return [
        'albums' => get_album_options(true, $includePrivate),
        'tags' => get_tag_options(true, $includePrivate),
        'cameras' => $pdo->query($cameraSql)->fetchAll(PDO::FETCH_COLUMN)
    ];
}

configure_runtime();
