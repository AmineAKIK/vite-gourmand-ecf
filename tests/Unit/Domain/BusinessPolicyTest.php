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
            'quote.validity_days' => 30,
            'material.return_days' => 7,
            'material.late_fee_cents' => 2500,
            'reminder.order_days_before' => ['7', '2'],
        ];
        $policy = new BusinessPolicy(static fn(string $key): mixed => $values[$key] ?? null);

        self::assertSame(48, $policy->minimumOrderLeadHours());
        self::assertSame(365, $policy->maximumOrderAdvanceDays());
        self::assertSame(72, $policy->customerCancellationCutoffHours());
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

    public function testOrderScheduleUsesConfiguredLeadTimeAndHorizon(): void
    {
        $values = [
            'order.minimum_lead_hours' => 48,
            'order.maximum_advance_days' => 90,
        ];
        $policy = new BusinessPolicy(static fn(string $key): mixed => $values[$key] ?? null);
        $now = new DateTimeImmutable('2026-08-26 10:00:00');

        $policy->assertOrderSchedule(new DateTimeImmutable('2026-08-28 10:00:00'), $now);
        self::assertTrue(true);

        $this->expectException(InvalidArgumentException::class);
        $policy->assertOrderSchedule(new DateTimeImmutable('2026-08-27 09:59:59'), $now);
    }
}
