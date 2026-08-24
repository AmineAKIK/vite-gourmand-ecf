<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\InventoryMovementPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InventoryMovementPolicyTest extends TestCase
{
    public function testOrderConsumptionKeyIsStablePerOrderAndIngredient(): void
    {
        self::assertSame('order:42:consume:7', InventoryMovementPolicy::orderConsumptionKey(42, 7));
        self::assertSame('order:42:consume:7', InventoryMovementPolicy::orderConsumptionKey(42, 7));
        self::assertNotSame(
            InventoryMovementPolicy::orderConsumptionKey(42, 7),
            InventoryMovementPolicy::orderConsumptionKey(42, 8),
        );
    }

    public function testReversalTypeNegatesLedgerEffect(): void
    {
        self::assertSame('sortie', InventoryMovementPolicy::reversalType('entree'));
        self::assertSame('entree', InventoryMovementPolicy::reversalType('sortie'));
        self::assertSame('sortie', InventoryMovementPolicy::reversalType('ajustement'));
    }

    public function testReversalKeyIsStable(): void
    {
        self::assertSame('stock:reversal:99', InventoryMovementPolicy::reversalKey(99));
    }

    public function testRejectsInvalidMovementType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryMovementPolicy::assertType('suppression');
    }

    public function testRejectsNonPositiveQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryMovementPolicy::assertQuantity(0.0);
    }

    public function testRejectsInvalidIdsForIdempotencyKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryMovementPolicy::orderConsumptionKey(0, 1);
    }
}
