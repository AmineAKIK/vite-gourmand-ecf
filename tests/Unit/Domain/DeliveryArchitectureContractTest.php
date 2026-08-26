<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class DeliveryArchitectureContractTest extends TestCase
{
    public function testDeliveryResolverContainsNoProviderOrCommercialPolicyDetails(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Geo/DeliveryResolver.php');
        self::assertIsString($source);

        foreach ([
            'nominatim',
            'api-adresse',
            'delivery.radius_km',
            'delivery.base_fee',
            'delivery.per_km_fee',
            'delivery.free_postal_codes',
            'computeDeliveryPrice',
            'deliveryQuote',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source, $forbidden);
        }
    }

    public function testGeocodingProviderBoundaryIsExplicitAndFranceAdapterOwnsExternalApis(): void
    {
        $contract = file_get_contents(dirname(__DIR__, 3) . '/src/Geo/GeocodingProvider.php');
        $adapter = file_get_contents(dirname(__DIR__, 3) . '/src/Geo/FranceGeocodingProvider.php');
        self::assertIsString($contract);
        self::assertIsString($adapter);

        self::assertStringContainsString('interface GeocodingProvider', $contract);
        self::assertStringContainsString('implements GeocodingProvider', $adapter);
        self::assertStringContainsString('nominatim.openstreetmap.org', $adapter);
        self::assertStringContainsString('api-adresse.data.gouv.fr', $adapter);
    }

    public function testOrderPricingAndPublicDeliveryEndpointShareOneQuoteService(): void
    {
        $pricing = file_get_contents(dirname(__DIR__, 3) . '/src/Services/PricingService.php');
        $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controllers/CommandeController.php');
        self::assertIsString($pricing);
        self::assertIsString($controller);

        self::assertStringContainsString('DeliveryQuoteService::quote', $pricing);
        self::assertStringContainsString('DeliveryQuoteService::quote', $controller);
        self::assertStringNotContainsString('computeDeliveryPriceCents', $pricing);
        self::assertStringNotContainsString('computeDeliveryPriceCents', $controller);
        self::assertStringNotContainsString('resolveAdresseLivraison', $controller);
        self::assertStringNotContainsString('distanceKmDepuisCoordonnees', $controller);
    }

    public function testLegacyDeliveryPolicyFacadeAndExceptionAreRemoved(): void
    {
        $siteConfig = file_get_contents(dirname(__DIR__, 3) . '/src/Config/SiteConfig.php');
        $helpers = file_get_contents(dirname(__DIR__, 3) . '/src/helpers.php');
        self::assertIsString($siteConfig);
        self::assertIsString($helpers);

        foreach ([
            'deliveryRadiusKm',
            'freePostalCodes',
            'deliveryBaseCents',
            'deliveryPerKmCents',
            'deliveryPricingLabel',
            'isGeoConfigured',
        ] as $legacyMethod) {
            self::assertStringNotContainsString('function ' . $legacyMethod . '(', $siteConfig, $legacyMethod);
        }

        foreach ([
            'distanceKmDepuisCoordonnees',
            'livraisonBaseCents',
            'livraisonPerKmCents',
            'livraisonRayonMaxKm',
            'livraisonGeoConfigured',
            'sitePostalCodesFree',
        ] as $legacyHelper) {
            self::assertStringNotContainsString('function ' . $legacyHelper . '(', $helpers, $legacyHelper);
        }

        self::assertFileDoesNotExist(
            dirname(__DIR__, 3) . '/src/Geo/Exception/DeliveryGeoNotConfiguredException.php'
        );
    }

    public function testProviderOutageAndConfigurationIncompleteRemainDistinct503Boundaries(): void
    {
        $providerException = file_get_contents(
            dirname(__DIR__, 3) . '/src/Geo/Exception/DeliveryProviderUnavailableException.php'
        );
        $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controllers/CommandeController.php');
        self::assertIsString($providerException);
        self::assertIsString($controller);

        self::assertStringContainsString('extends \\RuntimeException', $providerException);
        self::assertStringContainsString('catch (ConfigurationIncompleteException)', $controller);
        self::assertStringContainsString('catch (DeliveryProviderUnavailableException)', $controller);
    }

    public function testNoTemporaryDeliveryFinalizerArtifactsRemain(): void
    {
        self::assertFileDoesNotExist(
            dirname(__DIR__, 3) . '/bin/phase4_delivery_policy_finalize_clean.py'
        );
        self::assertFileDoesNotExist(
            dirname(__DIR__, 3) . '/.github/workflows/phase4-delivery-policy-clean-once.yml'
        );
    }
}
