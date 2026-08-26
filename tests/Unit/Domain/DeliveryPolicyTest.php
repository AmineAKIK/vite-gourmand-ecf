<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\DeliveryPolicy;
use App\Geo\Exception\DeliveryOutOfRangeException;
use PHPUnit\Framework\TestCase;

final class DeliveryPolicyTest extends TestCase
{
    public function testFreePostalCodeDoesNotBypassDeliveryRadius(): void
    {
        $policy = new DeliveryPolicy(48.85, 2.35, 20, ['75001'], 500, 125);

        $this->expectException(DeliveryOutOfRangeException::class);
        $policy->priceCents(20.01, '75001');
    }

    public function testConfiguredFreePostalCodeIsFreeInsideRadius(): void
    {
        $policy = new DeliveryPolicy(48.85, 2.35, 20, ['75001'], 500, 125);

        self::assertSame(0, $policy->priceCents(8.25, '75001'));
    }

    public function testPaidDeliveryUsesBasePlusPerKilometreInCents(): void
    {
        $policy = new DeliveryPolicy(48.85, 2.35, 20, [], 500, 125);

        self::assertSame(1531, $policy->priceCents(8.25, '75002'));
    }

    public function testDistanceCalculationIsDeterministicAndPolicyFree(): void
    {
        self::assertSame(
            111.19,
            \App\Geo\DeliveryResolver::distanceKmBetween(0.0, 0.0, 0.0, 1.0),
        );
    }
}
