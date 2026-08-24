<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class InputPolicy
{
    public static function text(mixed $value, int $maxLength = 255, bool $required = false): string
    {
        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException('Valeur texte invalide.');
        }

        $normalized = trim((string) ($value ?? ''));
        if ($required && $normalized === '') {
            throw new InvalidArgumentException('Ce champ est obligatoire.');
        }
        if (mb_strlen($normalized) > $maxLength) {
            throw new InvalidArgumentException('Valeur trop longue.');
        }

        return $normalized;
    }

    public static function multiline(mixed $value, int $maxLength, bool $required = false): string
    {
        $normalized = self::text($value, $maxLength, $required);
        return str_replace(["\r\n", "\r"], "\n", $normalized);
    }

    public static function email(mixed $value, bool $required = true): string
    {
        $email = mb_strtolower(self::text($value, 254, $required));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Adresse email invalide.');
        }
        return $email;
    }

    public static function postalCode(mixed $value, bool $required = true): string
    {
        $postalCode = self::text($value, 5, $required);
        if ($postalCode !== '' && !preg_match('/^\d{5}$/', $postalCode)) {
            throw new InvalidArgumentException('Code postal invalide (5 chiffres requis).');
        }
        return $postalCode;
    }

    public static function date(mixed $value, bool $required = true): string
    {
        $date = self::text($value, 10, $required);
        if ($date === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        $invalid = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        if (!$parsed || $invalid || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Date invalide.');
        }
        return $date;
    }

    public static function time(mixed $value, bool $required = true): string
    {
        $time = self::text($value, 5, $required);
        if ($time === '') {
            return '';
        }
        $parsed = \DateTimeImmutable::createFromFormat('!H:i', $time);
        $errors = \DateTimeImmutable::getLastErrors();
        $invalid = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        if (!$parsed || $invalid || $parsed->format('H:i') !== $time) {
            throw new InvalidArgumentException('Format d\'heure invalide (HH:MM).');
        }
        return $time;
    }

    public static function positiveId(mixed $value, bool $required = true): int
    {
        $raw = self::text($value, 20, $required);
        if ($raw === '' && !$required) {
            return 0;
        }
        if (!preg_match('/^[1-9]\d*$/', $raw)) {
            throw new InvalidArgumentException('Identifiant invalide.');
        }
        return (int) $raw;
    }

    public static function integer(mixed $value, int $min, int $max): int
    {
        $raw = self::text($value, 20, true);
        if (!preg_match('/^-?\d+$/', $raw)) {
            throw new InvalidArgumentException('Valeur entière invalide.');
        }
        $number = (int) $raw;
        if ((string) $number !== ltrim($raw, '+') && !preg_match('/^-?0+$/', $raw)) {
            throw new InvalidArgumentException('Valeur entière invalide.');
        }
        if ($number < $min || $number > $max) {
            throw new InvalidArgumentException('Valeur entière hors limites.');
        }
        return $number;
    }

    public static function decimal(mixed $value, float $min, ?float $max = null): string
    {
        $raw = str_replace(',', '.', self::text($value, 40, true));
        if (!preg_match('/^-?(?:\d+|\d*\.\d+)$/', $raw) || !is_numeric($raw)) {
            throw new InvalidArgumentException('Valeur décimale invalide.');
        }
        $number = (float) $raw;
        if (!is_finite($number) || $number < $min || ($max !== null && $number > $max)) {
            throw new InvalidArgumentException('Valeur décimale hors limites.');
        }
        return $raw;
    }

    public static function coordinate(mixed $value, float $min, float $max): string
    {
        $raw = str_replace(',', '.', self::text($value, 40, false));
        if ($raw === '') {
            return '';
        }
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('Coordonnée invalide.');
        }
        $coordinate = (float) $raw;
        if (!is_finite($coordinate) || $coordinate < $min || $coordinate > $max) {
            throw new InvalidArgumentException('Coordonnée hors limites.');
        }
        return rtrim(rtrim(number_format($coordinate, 7, '.', ''), '0'), '.');
    }

    public static function token(mixed $value, int $maxLength = 256): string
    {
        $token = self::text($value, $maxLength, true);
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            throw new InvalidArgumentException('Jeton invalide.');
        }
        return $token;
    }
}
