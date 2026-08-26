<?php

declare(strict_types=1);

namespace App\Geo;

use App\Config\SiteConfig;
use App\Geo\Exception\DeliveryProviderUnavailableException;
use App\Integrations\ExternalServiceUnavailableException;
use App\Integrations\JsonHttpClient;

final class FranceGeocodingProvider implements GeocodingProvider
{
    /** @var array<string,array{expires:int,value:mixed}> */
    private static array $cache = [];

    public function geocodeCity(string $city): ?array
    {
        $city = trim($city);
        if ($city === '') {
            return null;
        }

        $cacheKey = 'city:' . LocationNormalizer::normalize($city);
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&q=' . urlencode($city . ', France');
        try {
            $data = JsonHttpClient::get($url, ['User-Agent: ' . self::userAgent()], 3);
        } catch (ExternalServiceUnavailableException|\UnexpectedValueException $e) {
            throw new DeliveryProviderUnavailableException('Le service de géocodage est temporairement indisponible.', 0, $e);
        }
        if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
            self::cachePut($cacheKey, false, 300);

            return null;
        }

        $result = [(float) $data[0]['lat'], (float) $data[0]['lon']];
        self::cachePut($cacheKey, $result, 3600);

        return $result;
    }

    public function resolveAddress(string $address, string $city, string $postalCode): ?array
    {
        $address = trim($address);
        $city = trim($city);
        $postalCode = trim($postalCode);
        if ($address === '' || $city === '' || preg_match('/^\d{5}$/', $postalCode) !== 1) {
            return null;
        }

        $query = trim($address . ' ' . $postalCode . ' ' . $city);
        $cacheKey = 'address:' . hash('sha256', LocationNormalizer::normalize($query));
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $url = 'https://api-adresse.data.gouv.fr/search/?limit=1&q=' . urlencode($query);
        try {
            $data = JsonHttpClient::get($url, ['User-Agent: ' . self::userAgent()], 4);
        } catch (ExternalServiceUnavailableException|\UnexpectedValueException $e) {
            throw new DeliveryProviderUnavailableException('Le service de validation d’adresse est temporairement indisponible.', 0, $e);
        }

        $feature = $data['features'][0] ?? null;
        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $coords = is_array($feature['geometry']['coordinates'] ?? null) ? $feature['geometry']['coordinates'] : [];
        $score = (float) ($props['score'] ?? 0);
        $apiPostalCode = (string) ($props['postcode'] ?? '');
        $apiCity = (string) ($props['city'] ?? '');
        $apiType = (string) ($props['type'] ?? '');

        if (
            is_array($feature)
            && $score >= .45
            && in_array($apiType, ['housenumber', 'street'], true)
            && $apiPostalCode === $postalCode
            && LocationNormalizer::normalize($apiCity) === LocationNormalizer::normalize($city)
            && isset($coords[0], $coords[1])
        ) {
            $result = [
                'label' => (string) ($props['label'] ?? $query),
                'city' => $apiCity,
                'postcode' => $apiPostalCode,
                'lat' => (float) $coords[1],
                'lng' => (float) $coords[0],
                'score' => $score,
                'fallback' => false,
            ];
            self::cachePut($cacheKey, $result, 3600);

            return $result;
        }

        self::cachePut($cacheKey, false, 300);

        return null;
    }

    private static function userAgent(): string
    {
        $name = preg_replace('/[\r\n]+/', ' ', SiteConfig::name()) ?? 'Tugeres';

        return trim($name) . '/1.0 (Tugeres delivery geocoding)';
    }

    private static function cacheGet(string $key): mixed
    {
        $entry = self::$cache[$key] ?? null;
        if (!is_array($entry)) {
            return null;
        }
        if ($entry['expires'] < time()) {
            unset(self::$cache[$key]);

            return null;
        }

        return $entry['value'];
    }

    private static function cachePut(string $key, mixed $value, int $ttl): void
    {
        if (count(self::$cache) >= 256) {
            array_shift(self::$cache);
        }
        self::$cache[$key] = ['expires' => time() + $ttl, 'value' => $value];
    }
}
