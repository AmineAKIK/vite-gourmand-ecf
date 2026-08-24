<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

final class OrderAdmissionPolicy
{
    public static function assertWithinLimits(
        int $currentDayCount,
        int $maxPerDay,
        int $currentMonthCount,
        int $maxPerMonth,
    ): void {
        if ($currentDayCount < 0 || $maxPerDay < 0 || $currentMonthCount < 0 || $maxPerMonth < 0) {
            throw new RuntimeException('Compteurs d’admission invalides.');
        }

        if ($maxPerDay > 0 && $currentDayCount >= $maxPerDay) {
            throw new RuntimeException(
                'Capacité journalière atteinte (' . $maxPerDay . ' commande(s)). Choisissez une autre date.',
            );
        }

        if ($maxPerMonth > 0 && $currentMonthCount >= $maxPerMonth) {
            throw new RuntimeException(
                'Quota mensuel atteint (' . $maxPerMonth . ' commandes). '
                . 'Passez au plan supérieur pour continuer à accepter des commandes.',
            );
        }
    }
}
