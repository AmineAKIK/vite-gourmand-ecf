<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testDecimalStringsConvertToCentsWithoutFloatArithmetic(): void
    {
        self::assertSame(1000, Money::fromDecimal(10));
        self::assertSame(1999, Money::fromDecimal('19.99'));
        self::assertSame(1001, Money::fromDecimal('10.005'));
        self::assertSame(-1001, Money::fromDecimal('-10.005'));
    }

    public function testCentsSerializeToExactDecimalString(): void
    {
        self::assertSame('19.99', Money::toDecimalString(1999));
        self::assertSame('-19.99', Money::toDecimalString(-1999));
    }

    public function testPercentageUsesIntegerBasisPoints(): void
    {
        self::assertSame(333, Money::percentageBasisPoints(999, 3333));
        self::assertSame(1000, Money::percentageBasisPoints(1000, 15000));
        self::assertSame(0, Money::percentageBasisPoints(1000, 0));
        self::assertSame(3333, Money::percentToBasisPoints('33.33'));
    }

    public function testProportionalAllocationRoundsDeterministically(): void
    {
        self::assertSame(333, Money::allocateProportionally(1000, 1, 3));
    }

    public function testInvalidAmountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('not-a-number');
    }
}
