<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

require_admin();

$pageTitle = 'Health Check - ' . app_name();

function health_row(string $label, string $status, string $details = ''): array
{
    return ['label' => $label, 'status' => $status, 'details' => $details];
}

function health_status_label(string $status): string
{
    return match ($status) {
        'ok' => 'OK',
        'warn' => 'Увага',
        default => 'Помилка',
    };
}

function health_status_class(string $status): string
{
    return match ($status) {
        'ok' => 'status-ok',
        'warn' => 'status-warn',
        default => 'status-error',
    };
}

function health_yes_no(bool $value): string
{
    return $value ? 'так' : 'ні';
}

function health_forwarded_proto_details(): string
{
    $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    if ($forwardedProto === '') {
        $forwardedProto = 'немає';
    }

    return 'trusted proxy: ' . health_yes_no(request_from_trusted_proxy()) . ', X-Forwarded-Proto: ' . $forwardedProto;
}

function health_trusted_proxy_https_status(): string
{
    if (trusted_forwarded_https_request()) {
        return 'ok';
    }

    if (request_from_trusted_proxy()) {
        return is_production() ? 'error' : 'warn';
    }

    return 'ok';
}

function health_legacy_original_count(): int
{
    $dir = uploads_path('originals');
    if (!is_dir($dir)) {
        return 0;
    }

    $count = 0;
    foreach (new DirectoryIterator($dir) as $file) {
        if ($file->isDot() || !$file->isFile()) {
            continue;
        }

        $name = $file->getFilename();
        if (in_array($name, ['.gitkeep', '.htaccess'], true)) {
            continue;
        }

        $count++;
    }

    return $count;
}

$runtimeRows = [];
$runtimeRows[] = health_row('PHP version', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'error', PHP_VERSION . ' (required: 8.2+)');
$runtimeRows[] = health_row('APP_ENV', is_production() ? 'ok' : 'warn', app_env());
$runtimeRows[] = health_row('APP_DEBUG', app_debug() ? 'warn' : 'ok', app_debug() ? 'true' : 'false');
$runtimeRows[] = health_row('APP_URL HTTPS', app_url_is_https() ? 'ok' : (is_production() ? 'error' : 'warn'), (string) app_config()['APP_URL']);
$runtimeRows[] = health_row('Прямий HTTPS', direct_https_request() ? 'ok' : (is_production() && !trusted_forwarded_https_request() ? 'error' : 'warn'), health_yes_no(direct_https_request()));
$runtimeRows[] = health_row('Trusted proxy HTTPS', health_trusted_proxy_https_status(), health_forwarded_proto_details());
$runtimeRows[] = health_row('Ефективний HTTPS', is_https_request() ? 'ok' : (is_production() ? 'error' : 'warn'), health_yes_no(is_https_request()));
$runtimeRows[] = health_row('upload_max_filesize', 'ok', (string) ini_get('upload_max_filesize'));
$runtimeRows[] = health_row('post_max_size', 'ok', (string) ini_get('post_max_size'));
$runtimeRows[] = health_row('memory_limit', 'ok', (string) ini_get('memory_limit'));

$extensionRows = [];
foreach (required_php_extensions() as $extension) {
    $loaded = extension_loaded($extension);
    $extensionRows[] = health_row('Розширення ' . $extension, $loaded ? 'ok' : 'error', $loaded ? 'enabled' : 'missing (required)');
}
$extensionRows[] = health_row('GD WebP support', function_exists('imagewebp') ? 'ok' : 'warn', function_exists('imagewebp') ? 'enabled' : 'missing (no next-gen WebP)');
$extensionRows[] = health_row('GD AVIF support', function_exists('imageavif') ? 'ok' : 'warn', function_exists('imageavif') ? 'enabled' : 'missing (no next-gen AVIF)');

$directoryRows = [];
foreach ([
    'storage/originals' => originals_path(),
    'storage/trash' => trash_path(),
    'storage/logs' => storage_path('logs'),
    'storage/sessions' => storage_path('sessions'),
    'public/uploads/large' => uploads_path('large'),
    'public/uploads/thumbnails' => uploads_path('thumbnails'),
] as $label => $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    $directoryRows[] = health_row($label, $writable ? 'ok' : 'error', 'exists: ' . health_yes_no($exists) . ', writable: ' . health_yes_no($writable));
}

$dbRows = [];
try {
    db()->query('SELECT 1');
    $dbRows[] = health_row('DB connection', 'ok', 'SELECT 1 passed');

    foreach (['admins', 'albums', 'photos', 'tags', 'photo_tags', 'login_attempts', 'share_links'] as $table) {
        $stmt = db()->query('SELECT COUNT(*) FROM `' . $table . '`');
        $dbRows[] = health_row('Table `' . $table . '`', 'ok', (string) $stmt->fetchColumn() . ' rows');
    }
} catch (Throwable $exception) {
    app_log_exception($exception, 'Admin health DB check failed');
    $dbRows[] = health_row('DB connection', 'error', $exception->getMessage());
}

$legacyCount = health_legacy_original_count();
$fileRows = [
    health_row('Legacy originals у public/uploads/originals', $legacyCount === 0 ? 'ok' : 'warn', (string) $legacyCount . ' file(s)'),
    health_row('public/uploads/.htaccess', is_file(public_path('uploads' . DIRECTORY_SEPARATOR . '.htaccess')) ? 'ok' : 'warn', health_yes_no(is_file(public_path('uploads' . DIRECTORY_SEPARATOR . '.htaccess')))),
    health_row('public/uploads/originals/.htaccess', is_file(uploads_path('originals', '.htaccess')) ? 'ok' : 'warn', health_yes_no(is_file(uploads_path('originals', '.htaccess')))),
];

function render_health_table(string $title, array $rows): void
{
    ?>
    <section class="health-panel">
        <h2><?= h($title) ?></h2>
        <div class="health-table-wrap">
            <table class="health-table">
                <thead>
                <tr>
                    <th>Перевірка</th>
                    <th>Статус</th>
                    <th>Деталі</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row['label']) ?></td>
                        <td><span class="status-pill <?= h(health_status_class($row['status'])) ?>"><?= h(health_status_label($row['status'])) ?></span></td>
                        <td><?= h($row['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Health Check</h1>
        <p>Швидка перевірка production/runtime-налаштувань, PHP-модулів, БД і writable-папок.</p>
    </div>
    <div class="toolbar-actions">
        <a class="button secondary" href="<?= h(url('admin/stats.php')) ?>">Статистика</a>
        <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">Назад в адмінку</a>
    </div>
</section>

<?php render_health_table('Runtime', $runtimeRows); ?>
<?php render_health_table('PHP extensions', $extensionRows); ?>
<?php render_health_table('Database', $dbRows); ?>
<?php render_health_table('Writable directories', $directoryRows); ?>
<?php render_health_table('File access / legacy', $fileRows); ?>

<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
