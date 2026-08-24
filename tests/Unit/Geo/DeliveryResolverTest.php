<?php

namespace Tests\Unit\Geo;

use App\Geo\DeliveryResolver;
use App\Geo\Exception\DeliveryGeoNotConfiguredException;
use App\Geo\Exception\DeliveryProviderUnavailableException;
use PHPUnit\Framework\TestCase;

final class DeliveryResolverTest extends TestCase
{
    public function testNormalizeLabelIsStableForAddressComparison(): void
    {
        self::assertSame('saint etienne', DeliveryResolver::normalizeLabel("  Saint-Étienne  "));
        self::assertSame('l hay les roses', DeliveryResolver::normalizeLabel("L’Haÿ-les-Roses"));
    }

    public function testProviderOutageRemainsCompatibleWithExisting503Boundary(): void
    {
        $exception = new DeliveryProviderUnavailableException('provider down');
        self::assertInstanceOf(DeliveryGeoNotConfiguredException::class, $exception);
    }
}
