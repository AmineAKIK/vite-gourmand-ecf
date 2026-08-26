<?php

namespace App\Config;

use App\Domain\BrandAsset;
use App\Domain\Money;
use App\Models\SiteConfigModel;
use App\Models\SiteImageModel;
use UnexpectedValueException;

class SiteConfig
{
    private static ?array $cache = null;

    /**
     * Compatibility access for persisted presentation fields not migrated to a canonical key yet.
     * New code must use Configuration directly.
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
        return self::requiredString('brand.name');
    }

    public static function slogan(): string
    {
        return self::optionalString('brand.slogan');
    }

    public static function domain(): string
    {
        return self::optionalString('brand.domain');
    }

    public static function email(): string
    {
        return self::requiredString('contact.email');
    }

    public static function phone(): string
    {
        return self::requiredString('contact.phone');
    }

    public static function address(): string
    {
        return self::optionalString('contact.address.line1');
    }

    public static function postalCode(): string
    {
        return self::optionalString('contact.address.postal_code');
    }

    public static function city(): string
    {
        return self::optionalString('contact.address.city');
    }

    public static function fullAddress(): string
    {
        $parts = array_filter([self::address(), trim(self::postalCode() . ' ' . self::city())]);
        return implode(', ', $parts);
    }

    public static function color(string $key = 'couleur_principale'): string
    {
        $canonical = match ($key) {
            'couleur_principale' => 'theme.primary_color',
            'couleur_secondaire' => 'theme.secondary_color',
            'couleur_fond' => 'theme.background_color',
            default => throw new UnexpectedValueException('Unknown theme color key: ' . $key),
        };

        return self::requiredString($canonical);
    }

    public static function discountThresholdCents(): int
    {
        return Money::fromDecimal(self::requiredString('discount.threshold'));
    }

    public static function discountRatePercent(): int
    {
        return self::requiredInt('discount.rate_percent');
    }

    public static function logoUrl(): ?string
    {
        try {
            $url = SiteImageModel::get(BrandAsset::LOGO);
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

    public static function commandesMaxParJour(): int
    {
        return self::requiredInt('order.capacity.max_per_day');
    }

    private static function requiredString(string $key): string
    {
        $value = Configuration::get($key);
        if (!is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException($key . ' must resolve to a non-empty string.');
        }

        return $value;
    }

    private static function optionalString(string $key): string
    {
        $value = Configuration::get($key);
        if ($value === null) {
            return '';
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException($key . ' must resolve to a string.');
        }

        return $value;
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
