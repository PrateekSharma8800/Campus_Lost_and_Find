<?php
/**
 * Test runner: php tests/run.php
 * Loads every tests/*Test.php file, then runs all registered tests.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    echo "\n" . basename($file) . "\n";
    require $file;
}

run_tests();
