<?php

declare(strict_types=1);

ob_start();
try {
    require_once __DIR__ . '/bootstrap.php';
} catch (Throwable $exception) {
    ob_end_clean();
    fwrite(STDERR, 'Test bootstrap failed before suites: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$testFiles = glob(__DIR__ . '/unit/*_test.php');
$passed = 0;
$failed = 0;
$skipped = 0;

echo "Running tests...\n\n";

foreach ($testFiles as $file) {
    echo "Running " . basename($file) . "...\n";
    try {
        require_once $file;
        echo "  [OK]\n";
        $passed++;
    } catch (TestSkipped $e) {
        echo "  [SKIP] " . $e->getMessage() . "\n";
        $skipped++;
    } catch (Throwable $e) {
        echo "  [FAIL] " . $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

echo "\nTest suites completed: $passed passed, $failed failed, $skipped skipped; "
    . (int) ($GLOBALS['mygallery_test_assertions'] ?? 0) . " assertions.\n";
if (TESTS_DB_REQUIRED && $skipped > 0) {
    echo "Test DB is required, so skipped suites are a failure.\n";
    exit(1);
}
if ($failed > 0) {
    exit(1);
}

ob_end_flush();
