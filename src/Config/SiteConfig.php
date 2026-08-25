<?php

namespace App\Config;

use App\Models\SiteConfigModel;
use App\Models\SiteImageModel;
use UnexpectedValueException;

class SiteConfig
{
    private static ?array $cache = null;

    /**
     * Legacy presentation access only. Commercial settings must use Configuration.
     */
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

        return (string) (self::$cache[$key] ?? $default);
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
            'couleur_fond' => '#FDF6EC',
        ];

        return self::get($key, $defaults[$key] ?? '#333333');
    }

    public static function lat(): float
    {
        return self::requiredFloat('delivery.origin.latitude');
    }

    public static function lng(): float
    {
        return self::requiredFloat('delivery.origin.longitude');
    }

    public static function isGeoConfigured(): bool
    {
        return Configuration::isConfigured('delivery.origin.latitude')
            && Configuration::isConfigured('delivery.origin.longitude');
    }

    public static function deliveryRadiusKm(): int
    {
        return self::requiredInt('delivery.radius_km');
    }

    /** @return list<string> */
    public static function freePostalCodes(): array
    {
        $value = Configuration::get('delivery.free_postal_codes');
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new UnexpectedValueException('delivery.free_postal_codes must resolve to a string list.');
        }

        return array_values(array_map('strval', $value));
    }

    public static function deliveryBase(): float
    {
        return self::requiredFloat('delivery.base_fee');
    }

    public static function deliveryKm(): float
    {
        return self::requiredFloat('delivery.per_km_fee');
    }

    public static function discountThreshold(): float
    {
        return self::requiredFloat('discount.threshold');
    }

    public static function discountRate(): float
    {
        return self::requiredFloat('discount.rate_percent');
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
        return self::requiredInt('order.capacity.max_per_day');
    }

    private static function requiredFloat(string $key): float
    {
        $value = Configuration::get($key);
        if (!is_float($value) && !is_int($value)) {
            throw new UnexpectedValueException($key . ' must resolve to a numeric value.');
        }

        return (float) $value;
    }

    private static function requiredInt(string $key): int
    {
        $value = Configuration::get($key);
        if (!is_int($value)) {
            throw new UnexpectedValueException($key . ' must resolve to an integer.');
        }

        return $value;
    }
}
