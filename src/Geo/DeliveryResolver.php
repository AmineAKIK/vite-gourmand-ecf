<?php

declare(strict_types=1);

namespace App\Geo;

final class DeliveryResolver
{
    private static ?GeocodingProvider $provider = null;

    public static function normalizeLabel(string $value): string
    {
        return LocationNormalizer::normalize($value);
    }

    /** @return array{0:float,1:float}|null */
    public static function geocodeCity(string $city): ?array
    {
        return self::provider()->geocodeCity($city);
    }

    /** @return array{label:string,city:string,postcode:string,lat:float,lng:float,score:float,fallback:bool}|null */
    public static function resolveAddress(string $address, string $city, string $postalCode): ?array
    {
        return self::provider()->resolveAddress($address, $city, $postalCode);
    }

    public static function distanceKmBetween(
        float $originLatitude,
        float $originLongitude,
        float $targetLatitude,
        float $targetLongitude,
    ): float {
        foreach ([
            [$originLatitude, -90.0, 90.0],
            [$targetLatitude, -90.0, 90.0],
            [$originLongitude, -180.0, 180.0],
            [$targetLongitude, -180.0, 180.0],
        ] as [$value, $min, $max]) {
            if (!is_finite($value) || $value < $min || $value > $max) {
                throw new \InvalidArgumentException('Coordonnées de livraison invalides.');
            }
        }

        $earthRadiusKm = 6371.0;
        $lat1 = deg2rad($originLatitude);
        $lon1 = deg2rad($originLongitude);
        $lat2 = deg2rad($targetLatitude);
        $lon2 = deg2rad($targetLongitude);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;
        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return round($earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public static function useProviderForTests(?GeocodingProvider $provider): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new \LogicException('Geocoding provider override is test-only.');
        }
        self::$provider = $provider;
    }

    private static function provider(): GeocodingProvider
    {
        return self::$provider ??= new FranceGeocodingProvider();
    }
}
