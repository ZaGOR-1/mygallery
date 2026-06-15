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

    $errorStatusCode = $statusCode;
    $errorTitle = $statusCode === 404 ? 'Сторінку не знайдено' : 'Помилка сервера';
    $errorMessage = $safeMessage;
    $errorDetails = app_debug() && $exception !== null ? $exception->getTraceAsString() : '';
    $errorPage = public_path($statusCode === 404 ? '404.php' : '500.php');

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

function required_php_extensions(): array
{
    return ['pdo', 'pdo_mysql', 'gd', 'fileinfo', 'exif', 'mbstring'];
}

function missing_php_extensions(): array
{
    return array_values(array_filter(
        required_php_extensions(),
        static fn (string $extension): bool => !extension_loaded($extension)
    ));
}

function ensure_required_php_extensions(): void
{
    $missing = missing_php_extensions();

    if (!empty($missing)) {
        app_http_error('Відсутні обов’язкові PHP-розширення: ' . implode(', ', $missing) . '.', 500);
    }
}

function db_config(): array
{
    $path = project_root_path('config' . DIRECTORY_SEPARATOR . 'database.php');

    if (!file_exists($path)) {
        throw new RuntimeException('Файл config/database.php не знайдено. Скопіюйте config/database.example.php і впишіть налаштування бази даних.');
    }

    $config = require $path;

    if (is_production()) {
        $user = (string) ($config['DB_USER'] ?? '');
        $password = (string) ($config['DB_PASSWORD'] ?? '');

        if ($user === 'root' || $password === '') {
            app_http_error('Небезпечна production-конфігурація БД: використайте окремого користувача з паролем.', 500);
        }
    }

    return $config;
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

        try {
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Database connection failed');
            throw $exception;
        }
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

    validate_runtime_config();
    ensure_required_php_extensions();
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

function direct_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function request_from_trusted_proxy(): bool
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $trustedProxies = app_config()['TRUSTED_PROXIES'] ?? [];

    return is_string($remoteAddr)
        && $remoteAddr !== ''
        && is_array($trustedProxies)
        && in_array($remoteAddr, $trustedProxies, true);
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionPath = storage_path('sessions');

    if (!is_dir($sessionPath) && !@mkdir($sessionPath, 0755, true)) {
        app_http_error('Не вдалося створити папку для PHP-сесій.', 500);
    }

    if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
        app_http_error('Папка PHP-сесій недоступна для запису.', 500);
    }

    if (!str_contains((string) session_save_path(), 'test_sessions')) {
        session_save_path($sessionPath);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_cache_limiter('');
    session_set_cookie_params(session_cookie_options());

    if (!@session_start() || session_status() !== PHP_SESSION_ACTIVE) {
        app_http_error('Не вдалося запустити PHP-сесію. Перевірте права на storage/sessions.', 500);
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

    if (is_production() && is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function admin_exists(int $adminId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM admins WHERE id = :id');
    $stmt->execute(['id' => $adminId]);

    return (int) $stmt->fetchColumn() > 0;
}

function fulltext_index_exists(string $indexName): bool
{
    static $cache = [];

    if (array_key_exists($indexName, $cache)) {
        return $cache[$indexName];
    }

    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'photos'
              AND INDEX_NAME = :index_name
              AND INDEX_TYPE = 'FULLTEXT'"
        );
        $stmt->execute(['index_name' => $indexName]);
        $cache[$indexName] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        app_log_exception($exception, 'Fulltext index check failed');
        $cache[$indexName] = false;
    }

    return $cache[$indexName];
}

function fulltext_boolean_query(string $search): string
{
    // MySQL boolean FULLTEXT treats characters like `-`, `+`, `~`, `*`, `(`, `)`
    // as operators. Extract only plain word tokens and add our own safe `+term*`
    // syntax to avoid accidental negative terms or syntax errors from user input.
    preg_match_all('/[\p{L}\p{N}_]+/u', $search, $matches);
    $terms = array_slice(array_values(array_unique(array_filter(
        $matches[0] ?? [],
        static fn (string $term): bool => mb_strlen($term, 'UTF-8') >= 2
    ))), 0, 8);

    if (empty($terms)) {
        return '';
    }

    return implode(' ', array_map(static fn (string $term): string => '+' . $term . '*', $terms));
}

function photo_search_condition(string $search, bool $includeOriginalName, array &$params): string
{
    if ($search === '') {
        return '';
    }

    $fulltextIndex = $includeOriginalName ? 'idx_photos_admin_search_fulltext' : 'idx_photos_public_search_fulltext';
    $fulltextQuery = fulltext_boolean_query($search);

    if ($fulltextQuery !== '' && fulltext_index_exists($fulltextIndex)) {
        $params['search_fulltext'] = $fulltextQuery;

        return $includeOriginalName
            ? 'MATCH(photos.title, photos.description, photos.original_name) AGAINST (:search_fulltext IN BOOLEAN MODE)'
            : 'MATCH(photos.title, photos.description) AGAINST (:search_fulltext IN BOOLEAN MODE)';
    }

    $params['search_title'] = '%' . $search . '%';
    $params['search_description'] = '%' . $search . '%';

    if ($includeOriginalName) {
        $params['search_original'] = '%' . $search . '%';

        return '(photos.title LIKE :search_title OR photos.description LIKE :search_description OR photos.original_name LIKE :search_original)';
    }

    return '(photos.title LIKE :search_title OR photos.description LIKE :search_description)';
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

    $id = filter_var($raw, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Некоректний альбом.');
    }

    return (int) $id;
}

function get_album_options(bool $withCounts = false): array
{
    if ($withCounts) {
        $stmt = db()->query(
            'SELECT albums.id, albums.name, COUNT(photos.id) AS photo_count
            FROM albums
            LEFT JOIN photos ON photos.album_id = albums.id
            GROUP BY albums.id, albums.name
            ORDER BY albums.name ASC'
        );

        return $stmt->fetchAll();
    }

    $stmt = db()->query('SELECT id, name FROM albums ORDER BY name ASC');

    return $stmt->fetchAll();
}

function album_exists(int $id): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM albums WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}

function find_or_create_album(string $name): int
{
    $name = clean_album_name($name);

    if ($name === '') {
        throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
    }

    $stmt = db()->prepare(
        'INSERT INTO albums (name) VALUES (:name)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $stmt->execute(['name' => $name]);

    return (int) db()->lastInsertId();
}

function resolve_album_id_from_post(): ?int
{
    $albumId = get_album_id_from_post('album_id');
    $newAlbumName = clean_album_name((string) ($_POST['new_album_name'] ?? ''));

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

function get_tag_options(bool $withCounts = false): array
{
    if ($withCounts) {
        $stmt = db()->query(
            'SELECT tags.id, tags.name, tags.slug, COUNT(photo_tags.photo_id) AS photo_count
            FROM tags
            LEFT JOIN photo_tags ON photo_tags.tag_id = tags.id
            GROUP BY tags.id, tags.name, tags.slug
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

function find_or_create_tag(string $name): int
{
    $name = clean_tag_name($name);

    if ($name === '') {
        throw new InvalidArgumentException('Назва тегу не може бути порожньою.');
    }

    $stmt = db()->prepare(
        'INSERT INTO tags (name, slug) VALUES (:name, :slug)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)'
    );
    $stmt->execute([
        'name' => $name,
        'slug' => tag_slug($name),
    ]);

    return (int) db()->lastInsertId();
}

function sync_photo_tags(int $photoId, array $tagNames): void
{
    $deleteStmt = db()->prepare('DELETE FROM photo_tags WHERE photo_id = :photo_id');
    $deleteStmt->execute(['photo_id' => $photoId]);

    if (empty($tagNames)) {
        return;
    }

    $insertStmt = db()->prepare('INSERT IGNORE INTO photo_tags (photo_id, tag_id) VALUES (:photo_id, :tag_id)');

    foreach ($tagNames as $tagName) {
        $tagId = find_or_create_tag($tagName);
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

function valid_trash_photo_filename(string $filename): bool
{
    return preg_match('/\A[a-f0-9]{32}-[0-9]+-[a-f0-9]{32}\.jpg\z/', $filename) === 1;
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

function photo_responsive_srcset(array $photo): string
{
    $items = [];
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');
    $filename = (string) ($photo['filename'] ?? '');

    if ($thumbnail !== '' && safe_existing_upload_file_path('thumbnails', $thumbnail) !== null) {
        $items[] = uploads_url('thumbnails', $thumbnail) . ' 600w';
    }

    if ($filename !== '' && safe_existing_upload_file_path('large', $filename) !== null) {
        $largeWidth = (int) ($photo['width'] ?? 0);
        $largeWidth = $largeWidth > 0 ? min($largeWidth, (int) app_config()['LARGE_MAX_WIDTH']) : (int) app_config()['LARGE_MAX_WIDTH'];

        if ($largeWidth > 600) {
            $items[] = uploads_url('large', $filename) . ' ' . $largeWidth . 'w';
        }
    }

    return implode(', ', $items);
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
    }

    $thumbnailPath = safe_existing_upload_file_path('thumbnails', $thumbnail);
    if ($thumbnailPath !== null) {
        $paths[] = $thumbnailPath;
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

function validate_photo_files_deletable(array $photo): array
{
    $errors = photo_filename_errors($photo);

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

    $manifestPath = trash_path($operationId . '.json');
    $manifest = [
        'operation_id' => $operationId,
        'photo_id' => isset($photo['id']) ? (int) $photo['id'] : null,
        'created_at' => date('c'),
        'files' => array_map(
            static fn (array $file): array => [
                'area' => $file['area'],
                'folder' => $file['folder'],
                'filename' => $file['filename'],
                'trash_filename' => $file['trash_filename'],
            ],
            $planned
        ),
    ];

    if (!@file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
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

function restore_moved_photo_files(array $trashOperation): array
{
    $errors = [];
    $movedFiles = $trashOperation['files'] ?? $trashOperation;

    for ($i = count($movedFiles) - 1; $i >= 0; $i--) {
        $file = resolve_trash_manifest_entry((array) $movedFiles[$i]);

        if ($file === null || $file['from'] === null || $file['trash'] === null) {
            $errors[] = 'Некоректний запис у журналі кошика.';
            continue;
        }

        if (is_file($file['trash']) && !rename($file['trash'], $file['from'])) {
            $errors[] = 'Не вдалося повернути файл ' . basename((string) $file['from']) . '.';
        }
    }

    if (isset($trashOperation['manifest']) && is_file((string) $trashOperation['manifest'])) {
        @unlink((string) $trashOperation['manifest']);
    }

    return $errors;
}

function remove_trashed_photo_files(array $trashOperation): array
{
    $errors = [];
    $movedFiles = $trashOperation['files'] ?? $trashOperation;

    foreach ($movedFiles as $file) {
        $file = resolve_trash_manifest_entry((array) $file);

        if ($file === null || $file['trash'] === null) {
            $errors[] = 'Некоректний запис у журналі кошика.';
            continue;
        }

        if (is_file($file['trash']) && !@unlink($file['trash'])) {
            $errors[] = 'Не вдалося остаточно видалити файл ' . basename((string) $file['trash']) . '.';
        }
    }

    if (isset($trashOperation['manifest']) && is_file((string) $trashOperation['manifest']) && !@unlink((string) $trashOperation['manifest'])) {
        $errors[] = 'Не вдалося видалити журнал операції кошика.';
    }

    return $errors;
}

configure_runtime();
