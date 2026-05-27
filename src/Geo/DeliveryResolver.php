<?php

namespace App\Geo;

use App\Config\SiteConfig;

class DeliveryResolver
{
    public static function knownLocations(): array
    {
        return [
            'bordeaux'          => ['postcodes' => ['33000', '33100', '33200', '33300', '33800'], 'coords' => [44.8378, -0.5792]],
            'merignac'          => ['postcodes' => ['33700'],  'coords' => [44.8448, -0.6564]],
            'pessac'            => ['postcodes' => ['33600'],  'coords' => [44.8058, -0.6305]],
            'talence'           => ['postcodes' => ['33400'],  'coords' => [44.8088, -0.5892]],
            'begles'            => ['postcodes' => ['33130'],  'coords' => [44.8077, -0.5488]],
            'cenon'             => ['postcodes' => ['33150'],  'coords' => [44.8558, -0.5328]],
            'lormont'           => ['postcodes' => ['33310'],  'coords' => [44.8792, -0.5256]],
            'floirac'           => ['postcodes' => ['33270'],  'coords' => [44.8327, -0.5278]],
            'bruges'            => ['postcodes' => ['33520'],  'coords' => [44.8829, -0.6120]],
            'gradignan'         => ['postcodes' => ['33170'],  'coords' => [44.7736, -0.6156]],
            'villenave d ornon' => ['postcodes' => ['33140'],  'coords' => [44.7733, -0.5679]],
            'le bouscat'        => ['postcodes' => ['33110'],  'coords' => [44.8662, -0.5984]],
        ];
    }

    public static function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['’', "'"], ' ', $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public static function knownLocation(string $ville, string $codePostal): ?array
    {
        $cityKey   = self::normalizeLabel($ville);
        $locations = self::knownLocations();
        if (!isset($locations[$cityKey]) || !in_array($codePostal, $locations[$cityKey]['postcodes'], true)) {
            return null;
        }
        return [
            'label'    => trim($codePostal . ' ' . $ville),
            'city'     => $ville,
            'postcode' => $codePostal,
            'lat'      => $locations[$cityKey]['coords'][0],
            'lng'      => $locations[$cityKey]['coords'][1],
            'score'    => 1,
            'fallback' => true,
        ];
    }

    public static function geocodeCity(string $ville): ?array
    {
        $key = self::normalizeLabel($ville);
        $fallback = [
            'bordeaux'          => [44.8378, -0.5792],
            'merignac'          => [44.8448, -0.6564],
            'merignac'          => [44.8448, -0.6564],
            'pessac'            => [44.8058, -0.6305],
            'talence'           => [44.8088, -0.5892],
            'begles'            => [44.8077, -0.5488],
            'cenon'             => [44.8558, -0.5328],
            'lormont'           => [44.8792, -0.5256],
            'floirac'           => [44.8327, -0.5278],
            'bruges'            => [44.8829, -0.6120],
            'gradignan'         => [44.7736, -0.6156],
            'villenave d ornon' => [44.7733, -0.5679],
            'le bouscat'        => [44.8662, -0.5984],
        ];

        if (isset($fallback[$key])) {
            return $fallback[$key];
        }

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
            return null;
        }

        return self::knownLocation($ville, $codePostal);
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

    public static function computeDeliveryPrice(string $adresse, string $ville, string $codePostal): ?float
    {
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

        $distance = self::distanceKmFromCoords((float)$resolved['lat'], (float)$resolved['lng']);
        return round(SiteConfig::deliveryBase() + (SiteConfig::deliveryKm() * $distance), 2);
    }
}
