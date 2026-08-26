<?php

namespace Tests\Unit\Domain;

use App\Config\ConfigurationInvalidException;
use App\Config\ConfigurationMissingException;
use App\Domain\BusinessPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BusinessPolicyTest extends TestCase
{
    public function testResolvesCommercialRulesWithoutDefaults(): void
    {
        $values = [
            'order.minimum_lead_hours' => 48,
            'order.maximum_advance_days' => 365,
            'order.cancellation_cutoff_hours' => 72,
            'order.blackout_dates' => ['2026-12-25', '2027-01-01'],
            'quote.validity_days' => 30,
            'material.return_days' => 7,
            'material.late_fee_cents' => 2500,
            'reminder.order_days_before' => ['7', '2'],
        ];
        $policy = new BusinessPolicy(static fn(string $key): mixed => $values[$key] ?? null);

        self::assertSame(48, $policy->minimumOrderLeadHours());
        self::assertSame(365, $policy->maximumOrderAdvanceDays());
        self::assertSame(72, $policy->customerCancellationCutoffHours());
        self::assertSame(['2026-12-25', '2027-01-01'], $policy->blackoutDates());
        self::assertTrue($policy->isBlackoutDate('2026-12-25'));
        self::assertSame(30, $policy->quoteValidityDays());
        self::assertSame(7, $policy->materialReturnDays());
        self::assertSame(2500, $policy->materialLateFeeCents());
        self::assertSame([7, 2], $policy->reminderDaysBefore());
    }

    public function testMissingCommercialRuleFailsClosed(): void
    {
        $policy = new BusinessPolicy(static fn(string $key): mixed => null);

        $this->expectException(ConfigurationMissingException::class);
        $policy->quoteValidityDays();
    }

    public function testInvalidReminderDaysFailClosed(): void
    {
        $policy = new BusinessPolicy(static fn(string $key): mixed => ['0', 'abc']);

        $this->expectException(ConfigurationInvalidException::class);
        $policy->reminderDaysBefore();
    }

    public function testInvalidBlackoutDateFailsClosed(): void
    {
        $values = ['order.blackout_dates' => ['25/12/2026']];
        $policy = new BusinessPolicy(static fn(string $key): mixed => $values[$key] ?? null);

        $this->expectException(ConfigurationInvalidException::class);
        $policy->blackoutDates();
    }

    public function testOrderScheduleUsesConfiguredLeadTimeHorizonAndBlackouts(): void
    {
        $values = [
            'order.minimum_lead_hours' => 48,
            'order.maximum_advance_days' => 90,
            'order.blackout_dates' => ['2026-09-10'],
        ];
        $policy = new BusinessPolicy(static fn(string $key): mixed => $values[$key] ?? null);
        $now = new DateTimeImmutable('2026-08-26 10:00:00');

        $policy->assertOrderSchedule(new DateTimeImmutable('2026-08-28 10:00:00'), $now);
        self::assertTrue(true);

        try {
            $policy->assertOrderSchedule(new DateTimeImmutable('2026-08-27 09:59:59'), $now);
            self::fail('Lead-time violation should fail.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $policy->assertOrderSchedule(new DateTimeImmutable('2026-09-10 12:00:00'), $now);
    }
}
