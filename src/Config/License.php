<?php

namespace App\Config;

use App\Domain\SignedEntitlement;
use App\Models\SiteConfigModel;

final class License
{
    private static ?array $cache = null;

    public static function mode(): string
    {
        return TUGERES_ENTITLEMENTS_MODE === 'signed' ? 'signed' : 'legacy';
    }

    public static function isSignedMode(): bool
    {
        return self::mode() === 'signed';
    }

    public static function entitlements(): array
    {
        if (!self::isSignedMode()) {
            throw new \RuntimeException('Les droits signés ne sont pas activés.');
        }
        if (self::$cache !== null) {
            return self::$cache;
        }

        $document = SiteConfigModel::get('license_document') ?? '';
        $publicKey = self::publicKeyPem();
        if ($document === '' || $publicKey === '') {
            throw new \RuntimeException('Licence signée non configurée.');
        }

        return self::$cache = SignedEntitlement::verify($document, $publicKey, self::runtimeDomain());
    }

    public static function isValid(): bool
    {
        try {
            if (self::isSignedMode()) {
                self::entitlements();
                return true;
            }

            $key = SiteConfigModel::get('license_key') ?? '';
            $domain = SiteConfigModel::get('license_domain') ?? '';
            $hash = SiteConfigModel::get('license_hash') ?? '';
            if ($key === '' || $domain === '' || $hash === '') {
                return false;
            }

            // Legacy compatibility only. This verifier is deliberately not used for entitlement decisions.
            $secret = 'tugeres_akiksystems_2025_' . $key;
            $legacyHash = hash_hmac('sha256', self::normalizeDomain($domain), $secret);
            return hash_equals($hash, $legacyHash);
        } catch (\Throwable $e) {
            error_log('[license] validation impossible: ' . $e->getMessage());
            return false;
        }
    }

    public static function installSignedDocument(string $documentJson): array
    {
        $publicKey = self::publicKeyPem();
        if ($publicKey === '') {
            throw new \RuntimeException('Clé publique de licence non configurée.');
        }

        $verified = SignedEntitlement::verify($documentJson, $publicKey, self::runtimeDomain());
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO site_config (cle, valeur) VALUES ('license_document', ?)
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
            );
            $stmt->execute([$documentJson]);
            foreach (['license_key', 'license_hash'] as $legacyKey) {
                $pdo->prepare('DELETE FROM site_config WHERE cle = ?')->execute([$legacyKey]);
            }
            $pdo->prepare(
                "INSERT INTO site_config (cle, valeur) VALUES ('license_domain', ?)
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
            )->execute([$verified['domain']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        self::$cache = $verified;
        return $verified;
    }

    public static function banner(): string
    {
        return '<div style="position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#b91c1c;color:#fff;text-align:center;padding:.5rem;font-size:.85rem;font-family:sans-serif;">'
            . 'Licence Tugères non activée — <a href="https://tugeres.fr" style="color:#fde68a;" target="_blank" rel="noopener">tugeres.fr</a>'
            . '</div>';
    }

    private static function publicKeyPem(): string
    {
        if (TUGERES_LICENSE_PUBLIC_KEY_B64 === '') {
            return '';
        }
        $decoded = base64_decode(TUGERES_LICENSE_PUBLIC_KEY_B64, true);
        if ($decoded === false || $decoded === '') {
            throw new \RuntimeException('Clé publique de licence mal encodée.');
        }
        return $decoded;
    }

    private static function runtimeDomain(): string
    {
        $configured = SiteConfigModel::get('site_domaine') ?? '';
        if ($configured !== '') {
            return self::normalizeDomain($configured);
        }
        return self::normalizeDomain((string) ($_SERVER['HTTP_HOST'] ?? ''));
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
