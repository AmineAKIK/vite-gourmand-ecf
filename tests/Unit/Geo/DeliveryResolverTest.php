<?php

declare(strict_types=1);

namespace Tests\Unit\Geo;

use App\Geo\DeliveryResolver;
use App\Geo\GeocodingProvider;
use PHPUnit\Framework\TestCase;

final class DeliveryResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        DeliveryResolver::useProviderForTests(null);
    }

    public function testNormalizeLabelIsStableForAddressComparison(): void
    {
        self::assertSame('saint etienne', DeliveryResolver::normalizeLabel("  Saint-Étienne  "));
        self::assertSame('l hay les roses', DeliveryResolver::normalizeLabel("L’Haÿ-les-Roses"));
    }

    public function testResolverDelegatesGeocodingToProviderBoundary(): void
    {
        $provider = new class implements GeocodingProvider {
            public function geocodeCity(string $city): ?array
            {
                return $city === 'Paris' ? [48.8566, 2.3522] : null;
            }

            public function resolveAddress(string $address, string $city, string $postalCode): ?array
            {
                return [
                    'label' => $address . ', ' . $postalCode . ' ' . $city,
                    'city' => $city,
                    'postcode' => $postalCode,
                    'lat' => 48.8566,
                    'lng' => 2.3522,
                    'score' => 1.0,
                    'fallback' => false,
                ];
            }
        };
        DeliveryResolver::useProviderForTests($provider);

        self::assertSame([48.8566, 2.3522], DeliveryResolver::geocodeCity('Paris'));
        self::assertSame(
            '1 rue de Rivoli, 75001 Paris',
            DeliveryResolver::resolveAddress('1 rue de Rivoli', 'Paris', '75001')['label'],
        );
    }
}
