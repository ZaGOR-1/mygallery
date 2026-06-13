<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$deletePublicCopies = in_array('--delete-public', $argv, true);
$dryRun = !in_array('--apply', $argv, true);
$legacyFiles = glob(uploads_path('originals') . DIRECTORY_SEPARATOR . '*.jpg') ?: [];

if (empty($legacyFiles)) {
    echo "Legacy originals у public/uploads/originals не знайдено.\n";
    exit(0);
}

echo ($dryRun ? "DRY RUN: " : "APPLY: ") . "знайдено legacy originals: " . count($legacyFiles) . "\n";

foreach ($legacyFiles as $legacyPath) {
    $filename = basename($legacyPath);

    if (!valid_photo_filename($filename)) {
        echo "skip invalid: $filename\n";
        continue;
    }

    $target = originals_path($filename);
    $hasPrivate = is_file($target);

    if (!$hasPrivate) {
        echo "move public/uploads/originals/$filename -> storage/originals/$filename\n";
        if (!$dryRun && !@rename($legacyPath, $target)) {
            fwrite(STDERR, "Не вдалося перенести $filename\n");
        }
        continue;
    }

    $same = hash_file('sha256', $legacyPath) === hash_file('sha256', $target);

    if ($same || $deletePublicCopies) {
        echo "delete public copy: public/uploads/originals/$filename" . ($same ? " [same hash]" : " [forced]") . "\n";
        if (!$dryRun && !@unlink($legacyPath)) {
            fwrite(STDERR, "Не вдалося видалити public-копію $filename\n");
        }
    } else {
        echo "keep for manual review: public/uploads/originals/$filename [private copy differs]\n";
    }
}

if ($dryRun) {
    echo "\nЗапустіть з --apply, щоб виконати. Додайте --delete-public, щоб видаляти public-копії навіть коли приватна копія відрізняється.\n";
}
