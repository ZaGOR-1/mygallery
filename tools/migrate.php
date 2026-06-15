<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

try {
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) NOT NULL,
        run_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $executed = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

    $migrationsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    $files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    sort($files);

    foreach ($files as $file) {
        $filename = basename($file);
        if (in_array($filename, $executed, true)) {
            continue;
        }

        echo "Running migration: {$filename}\n";
        $sql = file_get_contents($file);
        if ($sql !== false && trim($sql) !== '') {
            $pdo->exec($sql);
        }
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
        $stmt->execute([$filename]);
    }
    echo "All migrations applied.\n";
} catch (Throwable $e) {
    app_log_exception($e, 'Migration failed');
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
