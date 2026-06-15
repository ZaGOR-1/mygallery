<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SimpleZipWriter.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$root = dirname(__DIR__);
$projectName = 'mygallery';
$version = trim((string) (@file_get_contents($root . DIRECTORY_SEPARATOR . 'VERSION') ?: 'v5'));
$versionSlug = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', strtolower($version)) ?: 'v5';
$distDir = $root . DIRECTORY_SEPARATOR . 'dist';
$output = $distDir . DIRECTORY_SEPARATOR . 'mygallery_' . $versionSlug . '_release.zip';

$options = getopt('', ['output:', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/build_release.php\n";
    echo "  php tools/build_release.php --output=/path/to/mygallery_release.zip\n";
    exit(0);
}

if (isset($options['output']) && is_string($options['output']) && $options['output'] !== '') {
    $output = $options['output'];
}

function release_relative_path(string $root, string $path): string
{
    $relative = substr($path, strlen($root));
    $relative = str_replace('\\', '/', $relative);

    return trim($relative, '/');
}

function release_should_exclude(string $relative, bool $isDir): bool
{
    $relative = trim(str_replace('\\', '/', $relative), '/');
    $name = basename($relative);

    if ($relative === '') {
        return false;
    }

    $excludedDirs = [
        '.git',
        'dist',
        'backups',
        'vendor',
        'node_modules',
        'alembic',
        'app/bot',
        'app/database',
        'app/services',
        'app/utils',
        'scripts',
        'tests',
        'storage/test_sessions',
        'storage/download_locks',
    ];

    foreach ($excludedDirs as $dir) {
        if ($relative === $dir || str_starts_with($relative, $dir . '/')) {
            return true;
        }
    }

    if ($relative === 'config/database.php' || $relative === '.env') {
        return true;
    }

    if (!$isDir && preg_match('/(^|\/)temp_[^\/]*\.php$/i', $relative)) {
        return true;
    }

    if (preg_match('/(^|\/)sess_[^\/]*$/', $relative)) {
        return true;
    }

    if (preg_match('/\.(zip|bak|backup|tmp|log)$/i', $name)) {
        return true;
    }

    if (str_starts_with($relative, 'storage/logs/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/sessions/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/trash/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/originals/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'public/uploads/large/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'public/uploads/thumbnails/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'public/uploads/originals/') && !in_array($name, ['.gitkeep', '.htaccess'], true)) {
        return true;
    }

    if (!$isDir && preg_match('/\.(jpg|jpeg|png|gif|webp|avif)$/i', $name)) {
        // Source code release should not contain uploaded user media.
        return true;
    }

    return false;
}

function release_forbidden_reason(string $entry): ?string
{
    $entry = trim(str_replace('\\', '/', $entry), '/');
    $withoutRoot = preg_replace('#^[^/]+/#', '', $entry) ?? $entry;
    $name = basename($withoutRoot);

    $checks = [
        '#(^|/)\.git(/|$)#' => '.git не можна додавати в release ZIP',
        '#(^|/)config/database\.php$#' => 'config/database.php містить локальні доступи',
        '#(^|/)\.env$#' => '.env містить секрети',
        '#(^|/)temp_[^/]*\.php$#i' => 'тимчасові PHP-міграції не можна додавати в release ZIP',
        '#(^|/)sess_[^/]*$#' => 'session-файли не можна додавати в ZIP',
        '#\.(log|bak|backup|tmp)$#i' => 'тимчасові/лог/backup-файли не можна додавати в ZIP',
        '#(^|/)storage/originals/.*\.(jpe?g|png|webp|avif)$#i' => 'оригінали фото не можна додавати в release ZIP',
        '#(^|/)public/uploads/(large|thumbnails|originals)/.*\.(jpe?g|png|webp|avif)$#i' => 'завантажені фото не можна додавати в release ZIP',
        '#(^|/)storage/(logs|sessions|trash|download_locks)/.+#' => 'runtime-файли storage не можна додавати в ZIP',
    ];

    foreach ($checks as $pattern => $reason) {
        if (preg_match($pattern, $withoutRoot)) {
            if ($name === '.gitkeep' || ($withoutRoot === 'public/uploads/originals/.htaccess')) {
                continue;
            }

            return $reason . ': ' . $withoutRoot;
        }
    }

    return null;
}

if (defined('TESTING_RELEASE_EXCLUSIONS')) {
    return;
}

if (!is_dir($distDir) && !mkdir($distDir, 0755, true)) {
    fwrite(STDERR, "Не вдалося створити папку dist.\n");
    exit(1);
}

if (is_file($output) && !unlink($output)) {
    fwrite(STDERR, "Не вдалося перезаписати старий ZIP: {$output}\n");
    exit(1);
}

$zip = new SimpleZipWriter($output);
$entries = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$zip->addDirectory($projectName);
$entries[] = $projectName . '/';

foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    $path = $item->getPathname();
    $relative = release_relative_path($root, $path);
    $isDir = $item->isDir();

    if (release_should_exclude($relative, $isDir)) {
        if ($isDir) {
            $iterator->next();
        }
        continue;
    }

    $entry = $projectName . '/' . $relative;
    $reason = release_forbidden_reason($entry);
    if ($reason !== null) {
        fwrite(STDERR, "Release build blocked: {$reason}\n");
        exit(1);
    }

    if ($isDir) {
        $zip->addDirectory($entry);
        $entries[] = rtrim($entry, '/') . '/';
        continue;
    }

    $zip->addFile($path, $entry);
    $entries[] = $entry;
}

foreach ($entries as $entry) {
    $reason = release_forbidden_reason($entry);
    if ($reason !== null) {
        fwrite(STDERR, "Release verification failed: {$reason}\n");
        exit(1);
    }
}

$zip->finish();

$size = filesize($output);
echo "Release ZIP створено: {$output}\n";
echo "Entries: " . count($entries) . "\n";
echo "Size: " . ($size === false ? 'unknown' : $size . ' bytes') . "\n";
echo "Перевірка небезпечних файлів: OK\n";
