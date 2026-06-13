<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$apply = in_array('--apply', $argv, true);
$purgeDeleted = in_array('--purge-deleted', $argv, true);
$manifests = glob(trash_path('*.json')) ?: [];

if (empty($manifests)) {
    echo "Журналів незавершених видалень не знайдено.\n";
    exit(0);
}

foreach ($manifests as $manifestPath) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        echo "Некоректний manifest: " . basename($manifestPath) . "\n";
        continue;
    }

    $photoId = (int) ($manifest['photo_id'] ?? 0);
    $exists = false;

    if ($photoId > 0) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM photos WHERE id = :id');
        $stmt->execute(['id' => $photoId]);
        $exists = (int) $stmt->fetchColumn() > 0;
    }

    echo basename($manifestPath) . ': photo_id=' . $photoId . ', db_row=' . ($exists ? 'exists' : 'missing') . "\n";

    foreach (($manifest['files'] ?? []) as $file) {
        $from = (string) ($file['from'] ?? '');
        $trash = (string) ($file['trash'] ?? '');

        if ($exists) {
            echo "  restore $trash -> $from\n";
            if ($apply && is_file($trash) && !is_file($from)) {
                @rename($trash, $from);
            }
        } elseif ($purgeDeleted) {
            echo "  purge $trash\n";
            if ($apply && is_file($trash)) {
                @unlink($trash);
            }
        }
    }

    if ($apply && (!$exists || $purgeDeleted)) {
        @unlink($manifestPath);
    }
}

if (!$apply) {
    echo "\nDRY RUN. Додайте --apply, щоб виконати. Додайте --purge-deleted, щоб остаточно чистити файли, записів яких уже немає в БД.\n";
}
