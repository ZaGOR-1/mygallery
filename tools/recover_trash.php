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
        $resolved = resolve_trash_manifest_entry((array) $file);

        if ($resolved === null || $resolved['from'] === null || $resolved['trash'] === null) {
            echo "  skip invalid manifest file entry\n";
            continue;
        }

        $from = $resolved['from'];
        $trash = $resolved['trash'];
        $filename = $resolved['filename'];

        if ($exists) {
            echo "  restore $filename\n";
            if ($apply && is_file($trash) && !is_file($from)) {
                if (!@rename($trash, $from)) {
                    echo "  restore failed: $filename\n";
                }
            }
        } elseif ($purgeDeleted) {
            echo "  purge $filename\n";
            if ($apply && is_file($trash)) {
                if (!@unlink($trash)) {
                    echo "  purge failed: $filename\n";
                }
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
