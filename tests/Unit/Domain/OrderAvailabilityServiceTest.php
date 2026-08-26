<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\BusinessPolicy;
use App\Services\OrderAvailabilityService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OrderAvailabilityServiceTest extends TestCase
{
    public function testAvailableDatePassesAllConfiguredGuards(): void
    {
        $decision = OrderAvailabilityService::decide(
            $this->policy(),
            new DateTimeImmutable('2026-09-10'),
            new DateTimeImmutable('2026-08-26 10:00:00'),
            2,
            5,
            10,
            50,
        );

        self::assertTrue($decision['available']);
        self::assertNull($decision['reason']);
        self::assertSame(2, $decision['count']);
        self::assertSame(10, $decision['month_count']);
    }

    public function testBlackoutDateWinsBeforeCapacityChecks(): void
    {
        $decision = OrderAvailabilityService::decide(
            $this->policy(['order.blackout_dates' => ['2026-09-10']]),
            new DateTimeImmutable('2026-09-10'),
            new DateTimeImmutable('2026-08-26 10:00:00'),
            0,
            5,
            0,
            50,
        );

        self::assertFalse($decision['available']);
        self::assertSame('blackout', $decision['reason']);
    }

    public function testLeadTimeAndAdvanceHorizonRejectWholeDates(): void
    {
        $tooSoon = OrderAvailabilityService::decide(
            $this->policy(['order.minimum_lead_hours' => 48]),
            new DateTimeImmutable('2026-08-27'),
            new DateTimeImmutable('2026-08-26 10:00:00'),
            0,
            5,
            0,
            50,
        );
        self::assertSame('lead_time', $tooSoon['reason']);

        $tooFar = OrderAvailabilityService::decide(
            $this->policy(['order.maximum_advance_days' => 30]),
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-08-26 10:00:00'),
            0,
            5,
            0,
            50,
        );
        self::assertSame('advance_horizon', $tooFar['reason']);
    }

    public function testDailyCapacityAndPlanQuotaUseSameThresholdSemanticsAsAdmission(): void
    {
        $fullDay = OrderAvailabilityService::decide(
            $this->policy(),
            new DateTimeImmutable('2026-09-10'),
            new DateTimeImmutable('2026-08-26 10:00:00'),
            5,
            5,
            10,
            50,
        );
        self::assertSame('day_capacity', $fullDay['reason']);

        $quota = OrderAvailabilityService::decide(
            $this->policy(),
            new DateTimeImmutable('2026-09-10'),
            new DateTimeImmutable('2026-08-26 10:00:00'),
            2,
            5,
            50,
            50,
        );
        self::assertSame('plan_quota', $quota['reason']);
    }

    private function policy(array $override = []): BusinessPolicy
    {
        $values = array_merge([
            'order.minimum_lead_hours' => 24,
            'order.maximum_advance_days' => 365,
            'order.blackout_dates' => [],
        ], $override);

        return new BusinessPolicy(static fn(string $key): mixed => $values[$key] ?? null);
    }
}
