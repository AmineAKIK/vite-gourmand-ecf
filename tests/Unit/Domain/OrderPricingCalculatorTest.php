<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\OrderPricingCalculator;
use PHPUnit\Framework\TestCase;

final class OrderPricingCalculatorTest extends TestCase
{
    public function testOrderWithoutDiscountKeepsGrossAndNetEqual(): void
    {
        $result = OrderPricingCalculator::calculate([
            ['menu_id' => 1, 'prix_par_personne' => '12.50', 'nombre_personne' => 4],
        ], 750, 10000, 10.0);

        self::assertSame(5000, $result['total_brut_cents']);
        self::assertSame(0, $result['remise_globale_cents']);
        self::assertSame(5000, $result['total_menus_net_cents']);
        self::assertSame(5750, $result['total_ttc_cents']);
        self::assertSame(5000, $result['lignes'][0]['prix_menu_net_cents']);
    }

    public function testThresholdDiscountIsAppliedExactlyOnce(): void
    {
        $result = OrderPricingCalculator::calculate([
            ['menu_id' => 1, 'prix_par_personne' => '25.00', 'nombre_personne' => 4],
        ], 1000, 10000, 10.0);

        self::assertSame(10000, $result['total_brut_cents']);
        self::assertSame(1000, $result['remise_globale_cents']);
        self::assertSame(9000, $result['total_menus_net_cents']);
        self::assertSame(10000, $result['total_ttc_cents']);
        self::assertSame(10000, $result['lignes'][0]['prix_menu_brut_cents']);
        self::assertSame(9000, $result['lignes'][0]['prix_menu_net_cents']);
    }

    public function testDiscountAllocationPreservesExactGlobalTotal(): void
    {
        $result = OrderPricingCalculator::calculate([
            ['menu_id' => 1, 'prix_par_personne' => '10.01', 'nombre_personne' => 1],
            ['menu_id' => 2, 'prix_par_personne' => '20.02', 'nombre_personne' => 1],
            ['menu_id' => 3, 'prix_par_personne' => '30.03', 'nombre_personne' => 1],
        ], 0, 1, 10.0);

        $allocated = array_sum(array_column($result['lignes'], 'remise_appliquee_cents'));
        $netLines = array_sum(array_column($result['lignes'], 'prix_menu_net_cents'));

        self::assertSame($result['remise_globale_cents'], $allocated);
        self::assertSame($result['total_menus_net_cents'], $netLines);
        self::assertSame(
            $result['total_brut_cents'] - $result['remise_globale_cents'],
            $result['total_menus_net_cents']
        );
    }

    public function testNegativeDeliveryIsClampedToZero(): void
    {
        $result = OrderPricingCalculator::calculate([
            ['menu_id' => 1, 'prix_par_personne' => '10.00', 'nombre_personne' => 1],
        ], -500, 0, 0.0);

        self::assertSame(0, $result['prix_livraison_cents']);
        self::assertSame(1000, $result['total_ttc_cents']);
    }
}
