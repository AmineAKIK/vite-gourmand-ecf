<?php

namespace App\Geo;

use App\Config\SiteConfig;
use App\Geo\Exception\DeliveryGeoNotConfiguredException;
use App\Geo\Exception\DeliveryOutOfRangeException;
use App\Integrations\ExternalServiceUnavailableException;
use App\Integrations\JsonHttpClient;

class DeliveryResolver
{
    /** @var array<string,array{expires:int,value:mixed}> */
    private static array $cache = [];

    public static function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['’', "'"], ' ', $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public static function geocodeCity(string $ville): ?array
    {
        $ville = trim($ville);
        if ($ville === '') {
            return null;
        }

        $cacheKey = 'city:' . self::normalizeLabel($ville);
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&q=' . urlencode($ville . ', France');
        $data = JsonHttpClient::get($url, ['User-Agent: ' . self::userAgent()], 3);
        if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
            self::cachePut($cacheKey, false, 300);
            return null;
        }

        $result = [(float) $data[0]['lat'], (float) $data[0]['lon']];
        self::cachePut($cacheKey, $result, 3600);
        return $result;
    }

    public static function resolveAddress(string $adresse, string $ville, string $codePostal): ?array
    {
        $adresse = trim($adresse);
        $ville = trim($ville);
        $codePostal = trim($codePostal);

        if ($adresse === '' || $ville === '' || !preg_match('/^\d{5}$/', $codePostal)) {
            return null;
        }

        $query = trim($adresse . ' ' . $codePostal . ' ' . $ville);
        $cacheKey = 'address:' . hash('sha256', self::normalizeLabel($query));
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $url = 'https://api-adresse.data.gouv.fr/search/?limit=1&q=' . urlencode($query);
        $data = JsonHttpClient::get($url, ['User-Agent: ' . self::userAgent()], 4);
        $feature = $data['features'][0] ?? null;
        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $coords = is_array($feature['geometry']['coordinates'] ?? null) ? $feature['geometry']['coordinates'] : [];
        $score = (float) ($props['score'] ?? 0);
        $apiPost = (string) ($props['postcode'] ?? '');
        $apiCity = (string) ($props['city'] ?? '');
        $apiType = (string) ($props['type'] ?? '');

        if (
            is_array($feature)
            && $score >= .45
            && in_array($apiType, ['housenumber', 'street'], true)
            && $apiPost === $codePostal
            && self::normalizeLabel($apiCity) === self::normalizeLabel($ville)
            && isset($coords[0], $coords[1])
        ) {
            $result = [
                'label' => (string) ($props['label'] ?? $query),
                'city' => $apiCity,
                'postcode' => $apiPost,
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

    public static function distanceKmFromCoords(float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $lat1 = deg2rad(SiteConfig::lat());
        $lon1 = deg2rad(SiteConfig::lng());
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * @throws DeliveryGeoNotConfiguredException
     * @throws DeliveryOutOfRangeException
     * @throws ExternalServiceUnavailableException
     */
    public static function deliveryQuote(string $adresse, string $ville, string $codePostal): ?array
    {
        if (!SiteConfig::isGeoConfigured()) {
            throw new DeliveryGeoNotConfiguredException(
                'Les coordonnées GPS du traiteur ne sont pas configurées. Veuillez renseigner la latitude et la longitude dans les paramètres.'
            );
        }

        $resolved = self::resolveAddress($adresse, $ville, $codePostal);
        if (!$resolved) {
            return null;
        }

        $distance = self::distanceKmFromCoords((float) $resolved['lat'], (float) $resolved['lng']);
        if (
            self::normalizeLabel($resolved['city'] ?? '') === self::normalizeLabel(SiteConfig::city())
            && in_array((string) ($resolved['postcode'] ?? ''), SiteConfig::freePostalCodes(), true)
        ) {
            return ['price' => 0.0, 'distance' => $distance, 'resolved' => $resolved];
        }

        $rayonMax = SiteConfig::deliveryRadiusKm();
        if ($distance > $rayonMax) {
            throw new DeliveryOutOfRangeException(
                sprintf(
                    'Cette adresse se trouve à %.1f km, au-delà du rayon de livraison de %d km.',
                    $distance,
                    $rayonMax,
                ),
                $distance,
                $rayonMax,
            );
        }

        return [
            'price' => round(SiteConfig::deliveryBase() + (SiteConfig::deliveryKm() * $distance), 2),
            'distance' => $distance,
            'resolved' => $resolved,
        ];
    }

    public static function computeDeliveryPrice(string $adresse, string $ville, string $codePostal): ?float
    {
        $quote = self::deliveryQuote($adresse, $ville, $codePostal);
        return $quote !== null ? (float) $quote['price'] : null;
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
