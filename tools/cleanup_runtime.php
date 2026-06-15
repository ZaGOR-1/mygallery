<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$apply = in_array('--apply', $argv, true);

$directoriesToClean = [
    'logs' => storage_path('logs'),
    'sessions' => storage_path('sessions'),
    'trash' => trash_path(),
];

// Configuration in seconds
$maxAgeLogs = 30 * 86400; // 30 days
$maxAgeSessions = 7 * 86400; // 7 days
$maxAgeTrash = 7 * 86400; // 7 days

$now = time();
$deletedFiles = 0;
$freedBytes = 0;

echo "Запуск очищення службових файлів виконання (runtime cleanup)...\n";

foreach ($directoriesToClean as $type => $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $maxAge = match ($type) {
        'logs' => $maxAgeLogs,
        'sessions' => $maxAgeSessions,
        'trash' => $maxAgeTrash,
        default => 0,
    };

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    $typeDeleted = 0;

    foreach ($iterator as $fileinfo) {
        $filename = $fileinfo->getFilename();
        if ($filename === '.gitkeep' || $filename === '.htaccess') {
            continue;
        }

        $path = $fileinfo->getRealPath();
        $mtime = $fileinfo->getMTime();
        
        if (($now - $mtime) > $maxAge) {
            if ($fileinfo->isFile()) {
                $size = $fileinfo->getSize();
                if ($apply) {
                    if (@unlink($path)) {
                        $typeDeleted++;
                        $deletedFiles++;
                        $freedBytes += $size;
                    } else {
                        fwrite(STDERR, "Не вдалося видалити: $path\n");
                    }
                } else {
                    echo "[DRY RUN] Would delete: $path (Age: " . round(($now - $mtime)/86400, 1) . " days)\n";
                    $typeDeleted++;
                }
            } elseif ($fileinfo->isDir()) {
                if ($apply) {
                    @rmdir($path);
                }
            }
        }
    }
    
    if ($apply && $typeDeleted > 0) {
        echo ucfirst($type) . ": видалено $typeDeleted старих файлів.\n";
    } elseif (!$apply && $typeDeleted > 0) {
        echo ucfirst($type) . ": знайдено $typeDeleted старих файлів для видалення.\n";
    }
}

if (!$apply) {
    echo "\nDRY RUN. Додайте --apply, щоб фактично видалити старі логи, сесії та файли кошика.\n";
} else {
    echo "\nОчищення успішно завершено.\n";
    echo "Загалом видалено файлів: $deletedFiles\n";
    echo "Звільнено місця: " . round($freedBytes / 1024 / 1024, 2) . " MB\n";
}

exit(0);
