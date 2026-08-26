<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Configuration;
use App\Config\PlanConfig;
use App\Config\SiteConfig;
use App\Domain\BusinessPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class OrderAvailabilityService
{
    /**
     * @return array{
     *   available:bool,
     *   reason:?string,
     *   message:?string,
     *   count:int,
     *   max:int,
     *   month_count:int,
     *   month_max:int
     * }
     */
    public static function checkDate(PDO $db, string $date, ?DateTimeImmutable $now = null): array
    {
        $date = trim($date);
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Date invalide.');
        }

        $now ??= new DateTimeImmutable();
        $policy = new BusinessPolicy(static fn(string $key): mixed => Configuration::get($key));
        $reason = self::scheduleReason($policy, $day, $now);

        $dayCount = OrderAdmissionService::countCommittedAndReservedForDay($db, $date);
        $dayMax = SiteConfig::commandesMaxParJour();
        $monthCount = OrderAdmissionService::countCommittedAndReservedForCurrentMonth($db);
        $monthMax = PlanConfig::maxCommandesMois();

        if ($reason === null && $dayMax > 0 && $dayCount >= $dayMax) {
            $reason = 'day_capacity';
        }
        if ($reason === null && $monthMax > 0 && $monthCount >= $monthMax) {
            $reason = 'plan_quota';
        }

        return [
            'available' => $reason === null,
            'reason' => $reason,
            'message' => self::message($reason, $dayMax, $monthMax),
            'count' => $dayCount,
            'max' => $dayMax,
            'month_count' => $monthCount,
            'month_max' => $monthMax,
        ];
    }

    public static function assertServiceAt(DateTimeImmutable $serviceAt, ?DateTimeImmutable $now = null): void
    {
        $policy = new BusinessPolicy(static fn(string $key): mixed => Configuration::get($key));
        $policy->assertOrderSchedule($serviceAt, $now ?? new DateTimeImmutable());
    }

    private static function scheduleReason(
        BusinessPolicy $policy,
        DateTimeImmutable $day,
        DateTimeImmutable $now,
    ): ?string {
        if ($policy->isBlackoutDate($day->format('Y-m-d'))) {
            return 'blackout';
        }

        $earliest = $now->modify('+' . $policy->minimumOrderLeadHours() . ' hours');
        $latest = $now->modify('+' . $policy->maximumOrderAdvanceDays() . ' days');
        $dayStart = $day->setTime(0, 0, 0);
        $dayEnd = $day->setTime(23, 59, 59);

        if ($dayEnd < $earliest) {
            return 'lead_time';
        }
        if ($dayStart > $latest) {
            return 'advance_horizon';
        }

        return null;
    }

    private static function message(?string $reason, int $dayMax, int $monthMax): ?string
    {
        return match ($reason) {
            'blackout' => 'Le traiteur est indisponible à cette date. Choisissez une autre date.',
            'lead_time' => 'Cette date ne respecte pas le délai minimum de commande configuré.',
            'advance_horizon' => 'Cette date dépasse l’horizon maximal de réservation configuré.',
            'day_capacity' => 'Capacité journalière atteinte' . ($dayMax > 0 ? ' (' . $dayMax . ' commande(s))' : '') . '. Choisissez une autre date.',
            'plan_quota' => 'Quota mensuel atteint' . ($monthMax > 0 ? ' (' . $monthMax . ' commandes)' : '') . '.',
            default => null,
        };
    }
}
