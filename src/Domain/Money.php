<?php

namespace App\Domain;

use InvalidArgumentException;

final class Money
{
    public static function fromDecimal(float|int|string $amount): int
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Montant monétaire invalide.');
        }

        return (int) round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function toDecimal(int $cents): float
    {
        return $cents / 100;
    }

    public static function percentage(int $cents, float $rate): int
    {
        if ($rate <= 0 || $cents <= 0) {
            return 0;
        }

        $boundedRate = min(100.0, $rate);

        return (int) round($cents * ($boundedRate / 100), 0, PHP_ROUND_HALF_UP);
    }
}
