<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/file_functions.php';

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

    $maxSize = 5 * 1024 * 1024; // 5 MB
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        @rename($logFile, $logFile . '.1');
    }

    if (!@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX)) {
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
            p.filename,
            p.thumbnail_filename,
            p.width,
            p.title AS cover_title,
            p.dominant_color,
            COUNT(p2.id) AS photo_count,
            MAX(p2.created_at) AS last_photo_at
        FROM albums a
        LEFT JOIN photos p ON p.id = CASE
            WHEN a.cover_photo_id IS NOT NULL THEN a.cover_photo_id
            ELSE (
                SELECT p3.id
                FROM photos p3
                WHERE p3.album_id = a.id
                ORDER BY p3.created_at DESC, p3.id DESC
                LIMIT 1
            )
        END
        LEFT JOIN photos p2 ON p2.album_id = a.id
        $where
        GROUP BY a.id, a.name, a.cover_photo_id, a.sort_order, a.is_private, p.filename, p.thumbnail_filename, p.width, p.title, p.dominant_color
        ORDER BY a.sort_order ASC, a.name ASC"
    );

    return $stmt->fetchAll();
}

function album_exists(int $id): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM albums WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}

function find_or_create_album(string $name, int $isPrivate = 0): int
{
    $name = clean_album_name($name);

    if ($name === '') {
        throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
    }

    $stmt = db()->prepare(
        'INSERT INTO albums (name, is_private) VALUES (:name, :is_private)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $stmt->execute(['name' => $name, 'is_private' => $isPrivate]);

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

function text_limit(string $text, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length);
    }

    return substr($text, 0, $length);
}

function normalize_gallery_filters(array $input): array
{
    $dateFrom = normalize_date_query((string) ($input['date_from'] ?? ''));
    $dateTo = normalize_date_query((string) ($input['date_to'] ?? ''));

    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $sort = (string) ($input['sort'] ?? 'newest');
    $sortOptions = ['newest', 'oldest', 'taken_newest', 'taken_oldest', 'title_az', 'title_za'];
    if (!in_array($sort, $sortOptions, true)) {
        $sort = 'newest';
    }

    $albumId = isset($input['album_id']) && $input['album_id'] !== '' ? (int) $input['album_id'] : null;
    $tagId = isset($input['tag_id']) && $input['tag_id'] !== '' ? (int) $input['tag_id'] : null;

    if ($albumId < 1) $albumId = null;
    if ($tagId < 1) $tagId = null;

    return [
        'q' => text_limit(trim((string) ($input['q'] ?? '')), 120),
        'camera' => text_limit(trim((string) ($input['camera'] ?? '')), 150),
        'album_id' => $albumId,
        'tag_id' => $tagId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sort,
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
}

function build_gallery_where_clause(array $filters, array &$params, bool $includeOriginalName = false): string
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

    // If not in admin view, exclude private albums
    if (!$includeOriginalName) {
        global $isSharedView;
        if (!($isSharedView ?? false)) {
            $where[] = '(albums.is_private IS NULL OR albums.is_private = 0)';
        }
    }

    $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

    return $joinSql . $whereSql;
}

function count_photos(PDO $pdo, array $filters, bool $includeOriginalName = false): int
{
    $params = [];
    $sqlSuffix = build_gallery_where_clause($filters, $params, $includeOriginalName);

    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT photos.id) FROM photos LEFT JOIN albums ON albums.id = photos.album_id' . $sqlSuffix);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function fetch_photos(PDO $pdo, array $filters, int $limit, int $offset, bool $includeOriginalName = false): array
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
    $sqlSuffix = build_gallery_where_clause($filters, $params, $includeOriginalName);

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
    return [
        'albums' => get_album_options(true, $includePrivate),
        'tags' => get_tag_options(true),
        'cameras' => $pdo
            ->query("SELECT DISTINCT camera_model FROM photos WHERE camera_model IS NOT NULL AND camera_model <> '' ORDER BY camera_model ASC")
            ->fetchAll(PDO::FETCH_COLUMN)
    ];
}

configure_runtime();
