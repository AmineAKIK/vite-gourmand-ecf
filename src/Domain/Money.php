<?php

namespace App\Domain;

use InvalidArgumentException;

final class Money
{
    public static function fromDecimal(string|int $amount): int
    {
        $raw = trim((string) $amount);
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $raw, $matches) !== 1) {
            throw new InvalidArgumentException('Montant monétaire invalide.');
        }

        $negative = ($matches[1] ?? '') === '-';
        $whole = (int) $matches[2];
        $fraction = $matches[3] ?? '';
        $centDigits = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ($whole * 100) + (int) $centDigits;

        $roundDigit = isset($fraction[2]) ? (int) $fraction[2] : 0;
        if ($roundDigit >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function toDecimalString(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $value = intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-' . $value : $value;
    }

    public static function percentageBasisPoints(int $cents, int $basisPoints): int
    {
        if ($cents <= 0 || $basisPoints <= 0) {
            return 0;
        }

        $boundedBasisPoints = min(10000, $basisPoints);
        return self::multiplyDivideRounded($cents, $boundedBasisPoints, 10000);
    }

    public static function percentToBasisPoints(string|int $percent): int
    {
        $basisPoints = self::fromDecimal($percent);
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('Pourcentage invalide.');
        }

        return $basisPoints;
    }

    public static function allocateProportionally(int $amountCents, int $partCents, int $totalCents): int
    {
        if ($amountCents <= 0 || $partCents <= 0 || $totalCents <= 0) {
            return 0;
        }

        return self::multiplyDivideRounded($amountCents, $partCents, $totalCents);
    }

    private static function multiplyDivideRounded(int $left, int $right, int $divisor): int
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Diviseur monétaire invalide.');
        }

        $product = $left * $right;
        return intdiv($product + intdiv($divisor, 2), $divisor);
    }
}
