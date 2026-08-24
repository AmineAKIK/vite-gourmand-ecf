<?php

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Config\Migrator;

try {
    Migrator::run();
    fwrite(STDOUT, "Migrations applied successfully.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
