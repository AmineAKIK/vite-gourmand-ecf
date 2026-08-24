<?php

namespace Tests\Unit\Domain;

use App\Domain\ReminderLeasePolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReminderLeasePolicyTest extends TestCase
{
    public function testUnsentReminderWithoutLeaseCanBeClaimed(): void
    {
        self::assertTrue(ReminderLeasePolicy::canClaim(null, null, new DateTimeImmutable('2026-08-25 10:00:00')));
    }

    public function testSentReminderCannotBeClaimed(): void
    {
        self::assertFalse(ReminderLeasePolicy::canClaim(
            '2026-08-25 09:00:00',
            null,
            new DateTimeImmutable('2026-08-25 10:00:00'),
        ));
    }

    public function testActiveLeaseCannotBeClaimed(): void
    {
        self::assertFalse(ReminderLeasePolicy::canClaim(
            null,
            '2026-08-25 10:05:00',
            new DateTimeImmutable('2026-08-25 10:00:00'),
        ));
    }

    public function testExpiredLeaseCanBeReclaimed(): void
    {
        self::assertTrue(ReminderLeasePolicy::canClaim(
            null,
            '2026-08-25 09:59:59',
            new DateTimeImmutable('2026-08-25 10:00:00'),
        ));
    }

    public function testErrorMessageIsBounded(): void
    {
        $error = new RuntimeException(str_repeat('x', 700));

        self::assertSame(500, mb_strlen(ReminderLeasePolicy::errorMessage($error)));
    }
}
