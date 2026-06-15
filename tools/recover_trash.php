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
$totalRestored = 0;
$totalPurged = 0;
$totalSkipped = 0;
$totalErrors = 0;

if (empty($manifests)) {
    echo "Журналів незавершених видалень не знайдено.\n";
    exit(0);
}

foreach ($manifests as $manifestPath) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        echo "Некоректний manifest: " . basename($manifestPath) . "\n";
        $totalErrors++;
        continue;
    }

    $photoId = (int) ($manifest['photo_id'] ?? 0);
    $exists = false;
    $manifestErrors = 0;
    $manifestActions = 0;

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
            $totalSkipped++;
            $manifestErrors++;
            continue;
        }

        $from = $resolved['from'];
        $trash = $resolved['trash'];
        $filename = $resolved['filename'];

        if ($exists) {
            echo "  restore $filename\n";

            if ($apply) {
                if (is_file($from)) {
                    echo "  already restored: $filename\n";
                    $manifestActions++;
                    continue;
                }

                if (!is_file($trash)) {
                    echo "  restore skipped, trash file missing: $filename\n";
                    $manifestErrors++;
                    $totalSkipped++;
                    continue;
                }

                if (!@rename($trash, $from)) {
                    echo "  restore failed: $filename\n";
                    $manifestErrors++;
                    $totalErrors++;
                    continue;
                }

                $manifestActions++;
                $totalRestored++;
            }
        } elseif ($purgeDeleted) {
            echo "  purge $filename\n";

            if ($apply) {
                if (!is_file($trash)) {
                    echo "  purge skipped, trash file missing: $filename\n";
                    $manifestActions++;
                    continue;
                }

                if (!@unlink($trash)) {
                    echo "  purge failed: $filename\n";
                    $manifestErrors++;
                    $totalErrors++;
                    continue;
                }

                $manifestActions++;
                $totalPurged++;
            }
        } else {
            $totalSkipped++;
        }
    }

    if ($apply) {
        $shouldRemoveManifest = false;

        if ($exists && $manifestErrors === 0) {
            $shouldRemoveManifest = true;
        }

        if (!$exists && $purgeDeleted && $manifestErrors === 0) {
            $shouldRemoveManifest = true;
        }

        if ($shouldRemoveManifest && is_file($manifestPath) && !@unlink($manifestPath)) {
            echo "  failed to remove manifest: " . basename($manifestPath) . "\n";
            $totalErrors++;
        } elseif ($shouldRemoveManifest) {
            echo "  manifest removed\n";
        }
    }
}

if (!$apply) {
    echo "\nDRY RUN. Додайте --apply, щоб виконати. Додайте --purge-deleted, щоб остаточно чистити файли, записів яких уже немає в БД.\n";
} else {
    echo "\nDONE. Restored: $totalRestored, purged: $totalPurged, skipped: $totalSkipped, errors: $totalErrors.\n";
}
