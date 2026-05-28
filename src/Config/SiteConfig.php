<?php

namespace App\Config;

use App\Models\SiteConfigModel;
use App\Models\SiteImageModel;

class SiteConfig
{
    private static ?array $cache = null;

    public static function get(string $key, string|float|int $default = ''): string
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                self::$cache = SiteConfigModel::getAll();
            } catch (\Throwable $e) {
                error_log('SiteConfig indisponible : ' . $e->getMessage());
            }
        }
        return (string)(self::$cache[$key] ?? $default);
    }

    public static function name(): string
    {
        return self::get('site_nom', 'Mon Traiteur');
    }

    public static function slogan(): string
    {
        return self::get('site_slogan', 'Traiteur');
    }

    public static function domain(): string
    {
        return self::get('site_domaine', $_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public static function email(): string
    {
        return self::get('site_email', MAIL_FROM);
    }

    public static function phone(): string
    {
        return self::get('site_telephone', '');
    }

    public static function address(): string
    {
        return self::get('site_adresse', '');
    }

    public static function postalCode(): string
    {
        return self::get('site_code_postal', '');
    }

    public static function city(): string
    {
        return self::get('site_ville', '');
    }

    public static function fullAddress(): string
    {
        $parts = array_filter([self::address(), trim(self::postalCode() . ' ' . self::city())]);
        return implode(', ', $parts);
    }

    public static function color(string $key = 'couleur_principale'): string
    {
        $defaults = [
            'couleur_principale' => '#8B1A2B',
            'couleur_secondaire' => '#D4A843',
            'couleur_fond'       => '#FDF6EC',
        ];
        return self::get($key, $defaults[$key] ?? '#333333');
    }

    public static function lat(): float
    {
        return (float)self::get('livraison_lat', 0.0);
    }

    public static function lng(): float
    {
        return (float)self::get('livraison_lng', 0.0);
    }

    public static function isGeoConfigured(): bool
    {
        return self::lat() !== 0.0 || self::lng() !== 0.0;
    }

    public static function deliveryRadiusKm(): int
    {
        $v = (int)self::get('livraison_rayon_max_km', 50);
        return $v > 0 ? $v : 50;
    }

    public static function freePostalCodes(): array
    {
        $raw = self::get('livraison_codes_postaux_gratuits', '');
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function deliveryBase(): float
    {
        return max(0.0, (float)self::get('livraison_base', LIVRAISON_BASE));
    }

    public static function deliveryKm(): float
    {
        return max(0.0, (float)self::get('livraison_km', LIVRAISON_KM));
    }

    public static function discountThreshold(): float
    {
        return max(0.0, (float)self::get('reduction_seuil', '100.00'));
    }

    public static function discountRate(): float
    {
        $rate = (float)self::get('reduction_taux', REDUCTION_TAUX * 100);
        return min(100.0, max(0.0, $rate));
    }

    public static function logoUrl(): ?string
    {
        try {
            $url = SiteImageModel::get('logo');
            return $url ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function slug(): string
    {
        $name = strtolower(self::name());
        $name = preg_replace('/[\s\-]+/', '_', $name);
        return preg_replace('/[^a-z0-9_]/', '', $name) ?: 'traiteur';
    }

    public static function deliveryPricingLabel(): string
    {
        return 'Livraison gratuite à ' . self::city() . '. '
            . number_format(self::deliveryBase(), 2, ',', ' ') . ' €'
            . ' + '
            . number_format(self::deliveryKm(), 2, ',', ' ')
            . ' €/km au-delà.';
    }

    public static function commandesMaxParJour(): int
    {
        return max(0, (int)self::get('commandes_max_par_jour', 0));
    }
}
