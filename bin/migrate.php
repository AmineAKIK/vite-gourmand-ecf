<?php

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Config\Migrator;

function isTransientDatabaseStartupFailure(\Throwable $error): bool
{
    for ($current = $error; $current !== null; $current = $current->getPrevious()) {
        if (!$current instanceof \PDOException) {
            continue;
        }

        $driverCode = (int) ($current->errorInfo[1] ?? 0);
        if (in_array($driverCode, [2002, 2003, 2006], true)) {
            return true;
        }
    }

    return false;
}

try {
    Migrator::run();
    fwrite(STDOUT, "Migrations applied successfully.\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(isTransientDatabaseStartupFailure($e) ? 75 : 1);
}
