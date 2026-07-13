<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli' || $argc !== 2 || app_env() !== 'test') {
    fwrite(STDERR, "Usage under APP_ENV=test: php tests/fixtures/assert_backup_fixture.php /path/to/snapshot.json\n");
    exit(1);
}
$expected = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$pdo = db();
foreach ((array) ($expected['counts'] ?? []) as $table => $count) {
    $actual = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', (string) $table) . '`')->fetchColumn();
    if ($actual !== (int) $count) {
        throw new RuntimeException("Fixture row-count mismatch for {$table}: {$actual} != {$count}");
    }
}
$title = $pdo->query('SELECT title FROM photos LIMIT 1')->fetchColumn();
if ($title !== ($expected['title'] ?? null)) {
    throw new RuntimeException('Unicode photo metadata changed across restore.');
}
foreach ((array) ($expected['files'] ?? []) as $relative => $descriptor) {
    $path = project_root_path(str_replace('/', DIRECTORY_SEPARATOR, (string) $relative));
    if (!is_file($path)
        || filesize($path) !== (int) $descriptor['size']
        || !hash_equals((string) $descriptor['sha256'], (string) hash_file('sha256', $path))) {
        throw new RuntimeException('Fixture media mismatch after restore: ' . $relative);
    }
}
echo "Backup fixture row counts, Unicode metadata and media hashes match.\n";
