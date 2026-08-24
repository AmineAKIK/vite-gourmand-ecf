<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\OrderAdmissionPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OrderAdmissionPolicyTest extends TestCase
{
    public function testUnlimitedLimitsAlwaysAllowAdmission(): void
    {
        OrderAdmissionPolicy::assertWithinLimits(500, 0, 5000, 0);
        self::assertTrue(true);
    }

    public function testLastAvailableSlotsAreAllowed(): void
    {
        OrderAdmissionPolicy::assertWithinLimits(4, 5, 49, 50);
        self::assertTrue(true);
    }

    public function testDailyCapacityIsRejectedAtLimit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Capacité journalière atteinte');

        OrderAdmissionPolicy::assertWithinLimits(5, 5, 10, 50);
    }

    public function testMonthlyQuotaIsRejectedAtLimit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Quota mensuel atteint');

        OrderAdmissionPolicy::assertWithinLimits(1, 5, 50, 50);
    }

    public function testReservedSlotCountsLikeCommittedCapacity(): void
    {
        // 4 commandes + 1 réservation active doivent bloquer la sixième admission.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Capacité journalière atteinte');

        OrderAdmissionPolicy::assertWithinLimits(5, 5, 12, 50);
    }

    public function testNegativeCountersAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Compteurs d’admission invalides');

        OrderAdmissionPolicy::assertWithinLimits(-1, 5, 0, 50);
    }
}
