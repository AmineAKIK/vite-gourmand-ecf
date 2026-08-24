<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\SignedEntitlement;

if ($argc < 5) {
    fwrite(STDERR, "Usage: php bin/sign-license.php <private-key.pem> <license-id> <domain> <starter|pro|premium> [expires-at]\n");
    exit(2);
}

$keyPath = $argv[1];
$licenseId = trim($argv[2]);
$domain = trim($argv[3]);
$plan = trim($argv[4]);
$expiresAt = isset($argv[5]) && $argv[5] !== '' ? $argv[5] : null;
$privateKey = is_file($keyPath) ? file_get_contents($keyPath) : false;
if (!is_string($privateKey) || $privateKey === '') {
    fwrite(STDERR, "Clé privée introuvable.\n");
    exit(2);
}

$payload = [
    'version' => 1,
    'license_id' => $licenseId,
    'domain' => $domain,
    'plan' => $plan,
    'issued_at' => date(DATE_ATOM),
    'expires_at' => $expiresAt,
];

try {
    $canonical = SignedEntitlement::canonicalPayload($payload);
    $signature = '';
    if (!openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Signature impossible.');
    }
    $payload['signature'] = base64_encode($signature);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
