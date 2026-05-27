<?php

namespace App\Core;

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
