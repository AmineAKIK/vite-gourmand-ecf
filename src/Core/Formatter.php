<?php

namespace App\Core;

use App\Domain\Money;

class Formatter
{
    public static function dateFr(?string $date, string $fallback = '—'): string
    {
        if (empty($date)) {
            return $fallback;
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
    }

    public static function dateTimeFr(?string $date, string $fallback = '—'): string
    {
        if (empty($date)) {
            return $fallback;
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y à H\hi', $timestamp) : $fallback;
    }

    public static function price(float|int|string|null $amount, int $decimals = 2): string
    {
        return number_format((float)($amount ?? 0), $decimals, ',', ' ') . ' €';
    }

    public static function moneyCents(int $cents): string
    {
        $decimal = Money::toDecimalString($cents);
        $negative = str_starts_with($decimal, '-');
        $unsigned = ltrim($decimal, '-');
        [$whole, $fraction] = explode('.', $unsigned, 2);
        $formatted = number_format((int) $whole, 0, ',', ' ') . ',' . $fraction . ' €';
        return $negative ? '-' . $formatted : $formatted;
    }

    public static function priceInput(float|int|string|null $amount): string
    {
        return number_format((float)($amount ?? 0), 2, '.', '');
    }

    public static function integer(float|int|string|null $amount): string
    {
        return number_format((float)($amount ?? 0), 0, ',', ' ');
    }

    public static function tomorrowDateInput(): string
    {
        return (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
    }

    public static function personFullName(array $person): string
    {
        return trim(($person['prenom'] ?? '') . ' ' . ($person['nom'] ?? ''));
    }

    public static function escape(?string $val): string
    {
        return htmlspecialchars(trim($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
