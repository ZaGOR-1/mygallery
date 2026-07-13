<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$apply = in_array('--apply', $argv, true);
$purgeDeleted = in_array('--purge-deleted', $argv, true);
$mediaMaintenanceLock = $apply ? acquire_media_maintenance_lock(LOCK_EX) : null;
recover_interrupted_trash_manifest_updates();
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
    $operationId = (string) ($manifest['operation_id'] ?? pathinfo($manifestPath, PATHINFO_FILENAME));
    $recoveryStatus = (string) ($manifest['status'] ?? 'ready');
    $exists = false;
    $manifestErrors = 0;
    $manifestActions = 0;

    if ($photoId > 0) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM photos WHERE id = :id');
        $stmt->execute(['id' => $photoId]);
        $exists = (int) $stmt->fetchColumn() > 0;
    }

    echo basename($manifestPath) . ': photo_id=' . $photoId . ', db_row=' . ($exists ? 'exists' : 'missing') . "\n";

    if (in_array($recoveryStatus, ['restore_in_progress', 'restore_committed'], true)) {
        echo "  resume interrupted restore ({$recoveryStatus})\n";
        if ($apply) {
            try {
                restore_photo_from_trash_unlocked(db(), $operationId);
                $totalRestored += count(is_array($manifest['files'] ?? null) ? $manifest['files'] : []);
                echo "  interrupted restore completed\n";
            } catch (Throwable $exception) {
                echo '  restore failed: ' . $exception->getMessage() . "\n";
                $totalErrors++;
            }
        }
        continue;
    }

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
                try {
                    install_trash_restore_file($resolved);
                    finalize_trash_restore_file($resolved);
                } catch (Throwable $exception) {
                    echo '  restore failed: ' . $exception->getMessage() . "\n";
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

release_media_maintenance_lock($mediaMaintenanceLock);
exit($totalErrors > 0 ? 1 : 0);
