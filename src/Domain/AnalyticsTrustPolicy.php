<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class AnalyticsTrustPolicy
{
    private const EXPORT_FORMATS = ['commandes', 'lignes', 'mensuel'];

    /** @return array{date_debut:string,date_fin:string} */
    public static function period(mixed $dateDebut, mixed $dateFin): array
    {
        $start = self::dateOrEmpty($dateDebut, 'Date de début invalide.');
        $end = self::dateOrEmpty($dateFin, 'Date de fin invalide.');

        if ($start !== '' && $end !== '' && $start > $end) {
            throw new InvalidArgumentException('La date de début doit précéder la date de fin.');
        }

        return ['date_debut' => $start, 'date_fin' => $end];
    }

    public static function optionalPositiveId(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Filtre menu invalide.');
        }

        return (int) $id;
    }

    public static function exportFormat(mixed $value): string
    {
        $format = trim((string) ($value ?? 'commandes'));
        if (!in_array($format, self::EXPORT_FORMATS, true)) {
            throw new InvalidArgumentException('Format d’export invalide.');
        }

        return $format;
    }

    public static function csvText(mixed $value): string
    {
        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        // Spreadsheet engines may evaluate user-controlled cells beginning with
        // =, +, -, @ (including after control/space prefixes) as formulas.
        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $text) === 1) {
            return "'" . $text;
        }

        return $text;
    }

    private static function dateOrEmpty(mixed $value, string $message): string
    {
        $date = trim((string) ($value ?? ''));
        if ($date === '') {
            return '';
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException($message);
        }

        return $date;
    }
}
