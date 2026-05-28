<?php

namespace App\Geo;

use App\Config\SiteConfig;
use App\Geo\Exception\DeliveryOutOfRangeException;
use App\Geo\Exception\DeliveryGeoNotConfiguredException;

class DeliveryResolver
{
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
        $url     = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&q=' . urlencode($ville . ', France');
        $context = stream_context_create([
            'http' => [
                'header'  => "User-Agent: " . SiteConfig::name() . "/1.0\r\n",
                'timeout' => 2,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if (!$response) {
            return null;
        }
        $data = json_decode($response, true);
        if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return null;
        }
        return [(float)$data[0]['lat'], (float)$data[0]['lon']];
    }

    public static function resolveAddress(string $adresse, string $ville, string $codePostal): ?array
    {
        $adresse    = trim($adresse);
        $ville      = trim($ville);
        $codePostal = trim($codePostal);

        if ($adresse === '' || $ville === '' || !preg_match('/^\d{5}$/', $codePostal)) {
            return null;
        }

        $query   = trim($adresse . ' ' . $codePostal . ' ' . $ville);
        $url     = 'https://api-adresse.data.gouv.fr/search/?limit=1&q=' . urlencode($query);
        $context = stream_context_create([
            'http' => [
                'header'  => "User-Agent: " . SiteConfig::name() . "/1.0\r\n",
                'timeout' => 3,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data       = json_decode($response, true);
            $feature    = $data['features'][0] ?? null;
            $props      = $feature['properties'] ?? [];
            $coords     = $feature['geometry']['coordinates'] ?? [];
            $score      = (float)($props['score'] ?? 0);
            $apiPost    = (string)($props['postcode'] ?? '');
            $apiCity    = (string)($props['city'] ?? '');
            $apiType    = (string)($props['type'] ?? '');

            if (
                $feature
                && $score >= .45
                && in_array($apiType, ['housenumber', 'street'], true)
                && $apiPost === $codePostal
                && self::normalizeLabel($apiCity) === self::normalizeLabel($ville)
                && isset($coords[0], $coords[1])
            ) {
                return [
                    'label'    => $props['label'] ?? $query,
                    'city'     => $apiCity,
                    'postcode' => $apiPost,
                    'lat'      => (float)$coords[1],
                    'lng'      => (float)$coords[0],
                    'score'    => $score,
                    'fallback' => false,
                ];
            }
        }

        return null;
    }

    public static function distanceKmFromCoords(float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $lat1        = deg2rad(SiteConfig::lat());
        $lon1        = deg2rad(SiteConfig::lng());
        $lat2        = deg2rad($lat2);
        $lon2        = deg2rad($lon2);
        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * @throws DeliveryGeoNotConfiguredException  si les coordonnées du traiteur ne sont pas configurées
     * @throws DeliveryOutOfRangeException         si l'adresse dépasse le rayon de livraison
     */
    public static function computeDeliveryPrice(string $adresse, string $ville, string $codePostal): ?float
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

        if (
            self::normalizeLabel($resolved['city'] ?? '') === self::normalizeLabel(SiteConfig::city())
            && in_array((string)($resolved['postcode'] ?? ''), SiteConfig::freePostalCodes(), true)
        ) {
            return 0.0;
        }

        $distance  = self::distanceKmFromCoords((float)$resolved['lat'], (float)$resolved['lng']);
        $rayonMax  = SiteConfig::deliveryRadiusKm();

        if ($distance > $rayonMax) {
            throw new DeliveryOutOfRangeException(
                sprintf(
                    'Cette adresse se trouve à %.1f km, au-delà du rayon de livraison de %d km.',
                    $distance,
                    $rayonMax
                ),
                $distance,
                $rayonMax
            );
        }

        return round(SiteConfig::deliveryBase() + (SiteConfig::deliveryKm() * $distance), 2);
    }
}
