<?php

namespace App\Config;

use App\Config\Database;
use App\Models\SiteConfigModel;

class License
{
    private static ?bool $cache = null;

    public static function isValid(): bool
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            if (!Database::isConnected()) {
                return self::$cache = true;
            }

            $key    = self::storedKey();
            $domain = self::storedDomain();

            if (!$key || !$domain) {
                return self::$cache = false;
            }

            return self::$cache = hash_equals(self::expectedHash($key, $domain), self::computeHash($key, $domain));
        } catch (\Throwable) {
            return self::$cache = true;
        }
    }

    public static function generate(string $domain): array
    {
        $key  = strtoupper(bin2hex(random_bytes(12)));
        $key  = implode('-', str_split($key, 6));
        $hash = self::computeHash($key, self::normalizeDomain($domain));

        return ['key' => $key, 'domain' => $domain, 'hash' => $hash];
    }

    public static function activate(string $key, string $domain): void
    {
        $domain = self::normalizeDomain($domain);
        $hash   = self::computeHash($key, $domain);

        $pdo = Database::getConnection();
        $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES ('license_key', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")->execute([$key]);
        $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES ('license_domain', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")->execute([$domain]);
        $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES ('license_hash', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")->execute([$hash]);

        self::$cache = null;
    }

    public static function banner(): string
    {
        return '<div style="position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#b91c1c;color:#fff;text-align:center;padding:.5rem;font-size:.85rem;font-family:sans-serif;">'
            . 'Licence Tugères non activée — <a href="https://tugeres.fr" style="color:#fde68a;" target="_blank">tugeres.fr</a>'
            . '</div>';
    }

    private static function storedKey(): string
    {
        return SiteConfigModel::get('license_key') ?? '';
    }

    private static function storedDomain(): string
    {
        return SiteConfigModel::get('license_domain') ?? '';
    }

    private static function expectedHash(string $key, string $domain): string
    {
        return SiteConfigModel::get('license_hash') ?? '';
    }

    private static function computeHash(string $key, string $domain): string
    {
        $secret = 'tugeres_akiksystems_2025_' . $key;
        return hash_hmac('sha256', $domain, $secret);
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        return $domain;
    }
}
