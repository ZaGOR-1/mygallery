<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$delete = in_array('--delete', $argv, true);

try {
    $photos = db()->query('SELECT filename, thumbnail_filename FROM photos')->fetchAll();
} catch (Throwable) {
    fwrite(STDERR, "Не вдалося підключитися до бази. Перевірте config/database.php.\n");
    exit(1);
}

$expected = [
    'storage:originals' => [],
    'public:originals' => [],
    'public:large' => [],
    'public:thumbnails' => [],
];

foreach ($photos as $photo) {
    $filename = (string) ($photo['filename'] ?? '');
    $thumbnail = (string) ($photo['thumbnail_filename'] ?? '');

    if (valid_photo_filename($filename)) {
        $expected['storage:originals'][$filename] = true;
        $expected['public:originals'][$filename] = true;
        $expected['public:large'][$filename] = true;
    }

    if (valid_photo_filename($thumbnail)) {
        $expected['public:thumbnails'][$thumbnail] = true;
    }
}

$orphans = [];

foreach ($expected as $location => $knownFiles) {
    [$root, $folder] = explode(':', $location, 2);
    $basePath = $root === 'storage' ? storage_path($folder) : uploads_path($folder);
    $files = glob($basePath . DIRECTORY_SEPARATOR . '*.jpg') ?: [];

    foreach ($files as $file) {
        $filename = basename($file);

        if (!isset($knownFiles[$filename])) {
            $orphans[] = [$root, $folder, $filename];
        }
    }
}

if (empty($orphans)) {
    echo "Orphan-файлів не знайдено.\n";
    exit(0);
}

echo "Знайдено orphan-файли: " . count($orphans) . "\n";

foreach ($orphans as [$root, $folder, $filename]) {
    echo $root . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename . "\n";
}

if (!$delete) {
    echo "\nЗапустіть з --delete, щоб видалити ці файли.\n";
    exit(0);
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

if ($errors > 0) {
    exit(1);
}

echo "Orphan-файли видалено.\n";
