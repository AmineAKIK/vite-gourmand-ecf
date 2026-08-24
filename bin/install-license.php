<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Config\License;

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php bin/install-license.php <license.json>\n");
    exit(2);
}

$document = is_file($argv[1]) ? file_get_contents($argv[1]) : false;
if (!is_string($document) || $document === '') {
    fwrite(STDERR, "Document de licence introuvable.\n");
    exit(2);
}

try {
    $verified = License::installSignedDocument($document);
    fwrite(STDOUT, 'Licence installée: ' . $verified['license_id'] . ' / ' . $verified['domain'] . ' / ' . $verified['plan'] . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
