<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/unit/*_test.php');
$passed = 0;
$failed = 0;

echo "Running tests...\n\n";

foreach ($testFiles as $file) {
    echo "Running " . basename($file) . "...\n";
    try {
        require_once $file;
        echo "  [OK]\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  [FAIL] " . $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

echo "\nTests completed: $passed passed, $failed failed.\n";
if ($failed > 0) {
    exit(1);
}
