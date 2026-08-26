<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final class InventoryQuantity
{
    public const SCALE = 3;
    public const UNITS = ['kg', 'g', 'L', 'cL', 'pièce', 'portion'];

    public static function normalizePositive(mixed $value): string
    {
        $normalized = self::normalize($value);
        if ($normalized === '0.000') {
            throw new InvalidArgumentException('La quantité doit être strictement positive.');
        }

        return $normalized;
    }

    public static function normalizeNonNegative(mixed $value): string
    {
        return self::normalize($value);
    }

    public static function milliunits(mixed $value): int
    {
        $normalized = self::normalize($value);
        [$whole, $fraction] = explode('.', $normalized, 2);

        return ((int) $whole * 1000) + (int) $fraction;
    }

    public static function fromMilliunits(int $milliunits): string
    {
        if ($milliunits < 0) {
            throw new InvalidArgumentException('La quantité ne peut pas être négative.');
        }

        return sprintf('%d.%03d', intdiv($milliunits, 1000), $milliunits % 1000);
    }

    public static function assertUnit(string $unit): string
    {
        $unit = trim($unit);
        if (!in_array($unit, self::UNITS, true)) {
            throw new InvalidArgumentException('Unité d’ingrédient non supportée.');
        }

        return $unit;
    }

    private static function normalize(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Quantité invalide.');
        }

        $raw = str_replace(',', '.', trim((string) $value));
        if ($raw === '' || preg_match('/^\d+(?:\.\d{1,3})?$/', $raw) !== 1) {
            throw new InvalidArgumentException('La quantité doit avoir au maximum trois décimales.');
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, self::SCALE, '0');

        return $whole . '.' . $fraction;
    }
}
