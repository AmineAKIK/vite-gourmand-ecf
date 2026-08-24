<?php

namespace App\Domain;

final class SignedEntitlement
{
    private const PLANS = ['starter', 'pro', 'premium'];

    public static function verify(string $documentJson, string $publicKeyPem, string $expectedDomain, ?int $now = null): array
    {
        $document = json_decode($documentJson, true);
        if (!is_array($document)) {
            throw new \RuntimeException('Document de licence invalide.');
        }

        $payload = self::payload($document);
        $signature = base64_decode((string) ($document['signature'] ?? ''), true);
        if ($signature === false || $signature === '') {
            throw new \RuntimeException('Signature de licence invalide.');
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new \RuntimeException('Clé publique de licence invalide.');
        }

        $verified = openssl_verify(self::canonicalPayload($payload), $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new \RuntimeException('Signature de licence non valide.');
        }

        $domain = self::normalizeDomain((string) $payload['domain']);
        if ($domain === '' || !hash_equals($domain, self::normalizeDomain($expectedDomain))) {
            throw new \RuntimeException('Licence non valable pour ce domaine.');
        }

        if (!in_array($payload['plan'], self::PLANS, true)) {
            throw new \RuntimeException('Plan de licence invalide.');
        }

        $now ??= time();
        if ($payload['expires_at'] !== null) {
            $expiresAt = strtotime($payload['expires_at']);
            if ($expiresAt === false || $expiresAt <= $now) {
                throw new \RuntimeException('Licence expirée.');
            }
        }

        return $payload;
    }

    public static function canonicalPayload(array $payload): string
    {
        $normalized = self::payload($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \RuntimeException('Impossible de sérialiser la licence.');
        }

        return $json;
    }

    private static function payload(array $document): array
    {
        $version = (int) ($document['version'] ?? 0);
        $licenseId = trim((string) ($document['license_id'] ?? ''));
        $domain = self::normalizeDomain((string) ($document['domain'] ?? ''));
        $plan = (string) ($document['plan'] ?? '');
        $issuedAt = trim((string) ($document['issued_at'] ?? ''));
        $expiresRaw = $document['expires_at'] ?? null;
        $expiresAt = $expiresRaw === null || $expiresRaw === '' ? null : trim((string) $expiresRaw);

        if ($version !== 1 || $licenseId === '' || $domain === '' || $issuedAt === '') {
            throw new \RuntimeException('Champs obligatoires de licence manquants.');
        }
        if (strtotime($issuedAt) === false) {
            throw new \RuntimeException('Date d’émission de licence invalide.');
        }

        return [
            'version' => 1,
            'license_id' => $licenseId,
            'domain' => $domain,
            'plan' => $plan,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ];
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain, 2)[0];
        $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;
        return rtrim($domain, '.');
    }
}
