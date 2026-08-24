<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\InputPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InputPolicyTest extends TestCase
{
    public function testTextStoresRawCharactersWithoutHtmlEncoding(): void
    {
        self::assertSame("Tom & Jerry <3", InputPolicy::text("  Tom & Jerry <3  ", 50, true));
    }

    public function testEmailIsTrimmedLowercasedAndValidated(): void
    {
        self::assertSame('client@example.com', InputPolicy::email(' Client@Example.COM '));
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::email('not-an-email');
    }

    public function testMultilineNormalizesLineEndings(): void
    {
        self::assertSame("ligne 1\nligne 2", InputPolicy::multiline("ligne 1\r\nligne 2", 100, true));
    }

    public function testPostalCodeMustHaveFiveDigits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::postalCode('7500');
    }

    public function testImpossibleDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::date('2026-02-31');
    }

    public function testTimeMustBeRealClockTime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::time('25:10');
    }

    public function testPositiveIdRejectsCastableGarbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::positiveId('12abc');
    }

    public function testIntegerRejectsDecimalString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::integer('3.9', 0, 10);
    }

    public function testLatitudeAndLongitudeUseDifferentBounds(): void
    {
        self::assertSame('48.8566', InputPolicy::coordinate('48,8566', -90, 90));
        self::assertSame('2.3522', InputPolicy::coordinate('2.3522', -180, 180));
    }

    public function testLatitudeOutsideWorldRangeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::coordinate('120', -90, 90);
    }

    public function testTokenRejectsUrlOrHtmlPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputPolicy::token('<script>');
    }
}
