<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$delete = in_array('--delete', $argv, true);

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
$missing = [];

foreach ($photos as $photo) {
    $id = (int) ($photo['id'] ?? 0);
    $filename = (string) ($photo['filename'] ?? '');
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');

    if (valid_photo_filename($filename)) {
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

foreach ($scanLocations as $location => $basePath) {
    [$root, $folder] = explode(':', $location, 2);
    $files = glob($basePath . DIRECTORY_SEPARATOR . '*.jpg') ?: [];

    foreach ($files as $file) {
        $filename = basename($file);
        $known = isset($expected[$location][$filename]);

        if (!$known || $location === 'public:originals') {
            $orphans[] = [$root, $folder, $filename, $location === 'public:originals' ? 'legacy public original' : 'orphan'];
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
        echo $root . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename . ' [' . $reason . "]\n";
    }
}

if (!$delete) {
    echo "\nЗапустіть з --delete, щоб видалити orphan/legacy-файли. Legacy originals краще спочатку перенести через tools/migrate_legacy_originals.php.\n";
    exit(empty($missing) ? 0 : 2);
}

$errors = 0;

foreach ($orphans as [$root, $folder, $filename]) {
    $path = $root === 'storage'
        ? safe_existing_storage_file_path($folder, $filename)
        : safe_existing_upload_file_path($folder, $filename);

    if ($path === null || !@unlink($path)) {
        $errors++;
        fwrite(STDERR, "Не вдалося видалити: " . $root . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename . "\n");
    }
}

exit($errors > 0 ? 1 : 0);
