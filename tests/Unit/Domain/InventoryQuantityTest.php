<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\InventoryQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InventoryQuantityTest extends TestCase
{
    public static function exactQuantities(): array
    {
        return [
            ['1', '1.000', 1000],
            ['0.1', '0.100', 100],
            ['0,125', '0.125', 125],
            ['12.345', '12.345', 12345],
            ['0002.5', '2.500', 2500],
        ];
    }

    #[DataProvider('exactQuantities')]
    public function testNormalizesInventoryQuantitiesExactly(string $input, string $expected, int $milliunits): void
    {
        self::assertSame($expected, InventoryQuantity::normalizePositive($input));
        self::assertSame($milliunits, InventoryQuantity::milliunits($input));
        self::assertSame($expected, InventoryQuantity::fromMilliunits($milliunits));
    }

    public function testSupportedUnitsAreExplicit(): void
    {
        self::assertSame(['kg', 'g', 'L', 'cL', 'pièce', 'portion'], InventoryQuantity::UNITS);
        foreach (InventoryQuantity::UNITS as $unit) {
            self::assertSame($unit, InventoryQuantity::assertUnit($unit));
        }
    }

    public function testRejectsMoreThanThreeDecimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryQuantity::normalizePositive('0.0001');
    }

    public function testRejectsFloatInputToAvoidBinaryRoundingAtBoundaries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryQuantity::normalizePositive(0.1);
    }

    public function testRejectsUnknownUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryQuantity::assertUnit('ml');
    }
}
