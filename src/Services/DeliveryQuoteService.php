<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\ConfigurationCompleteness;
use App\Domain\DeliveryPolicy;
use App\Geo\DeliveryResolver;
use InvalidArgumentException;

final class DeliveryQuoteService
{
    /** @return array{price_cents:int,distance_km:float,resolved:array}|null */
    public static function quote(string $address, string $city, string $postalCode): ?array
    {
        ConfigurationCompleteness::assertDeliveryReady();
        $resolved = DeliveryResolver::resolveAddress($address, $city, $postalCode);
        if ($resolved === null) {
            return null;
        }

        if (!isset($resolved['lat'], $resolved['lng'], $resolved['postcode'])) {
            throw new InvalidArgumentException('Adresse de livraison résolue invalide.');
        }

        $policy = DeliveryPolicy::fromConfiguration();
        $distanceKm = DeliveryResolver::distanceKmBetween(
            $policy->originLatitude(),
            $policy->originLongitude(),
            (float) $resolved['lat'],
            (float) $resolved['lng'],
        );

        return [
            'price_cents' => $policy->priceCents($distanceKm, (string) $resolved['postcode']),
            'distance_km' => $distanceKm,
            'resolved' => $resolved,
        ];
    }

    public static function priceCents(string $address, string $city, string $postalCode): ?int
    {
        $quote = self::quote($address, $city, $postalCode);
        return $quote === null ? null : $quote['price_cents'];
    }
}
