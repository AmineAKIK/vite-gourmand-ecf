<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testDecimalAmountsConvertToCents(): void
    {
        self::assertSame(1000, Money::fromDecimal(10));
        self::assertSame(1999, Money::fromDecimal('19.99'));
        self::assertSame(1001, Money::fromDecimal(10.005));
    }

    public function testCentsConvertBackToDecimal(): void
    {
        self::assertSame(19.99, Money::toDecimal(1999));
    }

    public function testPercentageRoundsOnceInCents(): void
    {
        self::assertSame(333, Money::percentage(999, 33.333));
        self::assertSame(1000, Money::percentage(1000, 150));
        self::assertSame(0, Money::percentage(1000, 0));
    }

    public function testInvalidAmountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('not-a-number');
    }
}
