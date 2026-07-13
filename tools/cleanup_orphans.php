<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$delete = in_array('--delete', $argv, true);
$mediaMaintenanceLock = $delete ? acquire_media_maintenance_lock(LOCK_EX) : null;

function display_media_path(string $root, string $folder, string $filename): string
{
    if ($root === 'public') {
        return 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename;
    }

    return $root . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename;
}

try {
    $photos = db()->query('SELECT id, filename, thumbnail_filename FROM photos')->fetchAll();
} catch (Throwable $exception) {
    app_log_exception($exception, 'Cleanup DB failed');
    fwrite(STDERR, "Не вдалося підключитися до бази. Перевірте config/database.php.\n");
    exit(1);
}

$expected = [
    'storage:originals' => [],
    'public:large' => [],
    'public:thumbnails' => [],
];
$referencedOriginals = [];
$missing = [];

foreach ($photos as $photo) {
    $id = (int) ($photo['id'] ?? 0);
    $filename = (string) ($photo['filename'] ?? '');
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');

    if (valid_photo_filename($filename)) {
        $referencedOriginals[$filename] = true;
        $expected['storage:originals'][$filename] = true;
        $expected['public:large'][$filename] = true;

        if (safe_existing_storage_file_path('originals', $filename) === null) {
            $missing[] = ['db_id' => $id, 'location' => 'storage/originals', 'filename' => $filename];
        }

        if (safe_existing_upload_file_path('large', $filename) === null) {
            $missing[] = ['db_id' => $id, 'location' => 'public/uploads/large', 'filename' => $filename];
        }
    } else {
        $missing[] = ['db_id' => $id, 'location' => 'db/photos.filename', 'filename' => $filename];
    }

    if (valid_photo_filename($thumbnail)) {
        $expected['public:thumbnails'][$thumbnail] = true;

        if (safe_existing_upload_file_path('thumbnails', $thumbnail) === null) {
            $missing[] = ['db_id' => $id, 'location' => 'public/uploads/thumbnails', 'filename' => $thumbnail];
        }
    } else {
        $missing[] = ['db_id' => $id, 'location' => 'db/photos.thumbnail_filename', 'filename' => $thumbnail];
    }
}

$scanLocations = [
    'storage:originals' => storage_path('originals'),
    'public:large' => uploads_path('large'),
    'public:thumbnails' => uploads_path('thumbnails'),
    'public:originals' => uploads_path('originals'),
];
$orphans = [];
$blockedLegacyOriginals = [];

foreach ($scanLocations as $location => $basePath) {
    [$root, $folder] = explode(':', $location, 2);
    $jpgs = glob($basePath . DIRECTORY_SEPARATOR . '*.jpg') ?: [];
    $webps = glob($basePath . DIRECTORY_SEPARATOR . '*.webp') ?: [];
    $avifs = glob($basePath . DIRECTORY_SEPARATOR . '*.avif') ?: [];
    $files = array_merge($jpgs, $webps, $avifs);

    foreach ($files as $file) {
        $filename = basename($file);
        $baseJpg = $filename;
        if (str_ends_with($filename, '.webp')) {
            $baseJpg = substr($filename, 0, -5) . '.jpg';
        } elseif (str_ends_with($filename, '.avif')) {
            $baseJpg = substr($filename, 0, -5) . '.jpg';
        }
        $known = isset($expected[$location][$baseJpg]);

        if ($location === 'public:originals' && str_ends_with($filename, '.jpg')) {
            $privatePath = safe_existing_storage_file_path('originals', $filename);
            $decision = legacy_original_cleanup_decision(
                $file,
                $privatePath,
                isset($referencedOriginals[$filename])
            );
            if ($decision['deletable']) {
                $orphans[] = [$root, $folder, $filename, $decision['reason']];
            } else {
                $blockedLegacyOriginals[] = [$filename, $decision['reason']];
            }
            continue;
        }

        if (!$known) {
            $orphans[] = [$root, $folder, $filename, 'orphan'];
        }
    }
}

if (!empty($missing)) {
    echo "Відсутні файли для DB-записів: " . count($missing) . "\n";
    foreach ($missing as $item) {
        echo '#'. $item['db_id'] . ' ' . $item['location'] . DIRECTORY_SEPARATOR . $item['filename'] . "\n";
    }
    echo "\n";
}

if (empty($orphans)) {
    echo "Orphan-файлів не знайдено.\n";
} else {
    echo "Знайдено orphan/legacy-файли: " . count($orphans) . "\n";
    foreach ($orphans as [$root, $folder, $filename, $reason]) {
        echo display_media_path($root, $folder, $filename) . ' [' . $reason . "]\n";
    }
}

if ($blockedLegacyOriginals !== []) {
    echo "\nLegacy originals, які не можна безпечно видалити: " . count($blockedLegacyOriginals) . "\n";
    foreach ($blockedLegacyOriginals as [$filename, $reason]) {
        echo display_media_path('public', 'originals', $filename) . ' [' . $reason . "]\n";
    }
}

if (!$delete) {
    echo "\nЗапустіть з --delete, щоб видалити тільки orphan-файли та verified duplicate legacy originals.\n";
    echo "Legacy-only або hash-mismatched originals спочатку перевірте через tools/migrate_legacy_originals.php.\n";
    exit(empty($missing) && $blockedLegacyOriginals === [] ? 0 : 2);
}

if ($blockedLegacyOriginals !== []) {
    fwrite(STDERR, "Видалення зупинено: не всі DB-referenced legacy originals мають перевірену ідентичну private copy.\n");
    fwrite(STDERR, "Спочатку запустіть tools/migrate_legacy_originals.php і повторіть dry-run.\n");
    release_media_maintenance_lock($mediaMaintenanceLock);
    exit(2);
}

$errors = 0;

foreach ($orphans as [$root, $folder, $filename]) {
    $path = $root === 'storage'
        ? safe_existing_storage_file_path($folder, $filename)
        : safe_existing_upload_file_path($folder, $filename);

    if ($path === null || !@unlink($path)) {
        $errors++;
        fwrite(STDERR, "Не вдалося видалити: " . display_media_path($root, $folder, $filename) . "\n");
    }
}

release_media_maintenance_lock($mediaMaintenanceLock);
exit($errors > 0 ? 1 : 0);
