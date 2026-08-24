<?php

namespace App\Domain;

use InvalidArgumentException;

final class DataRetentionPolicy
{
    public const DEFAULT_READ_NOTIFICATION_DAYS = 365;
    public const DEFAULT_SENT_REMINDER_DAYS = 180;
    public const DEFAULT_BATCH_SIZE = 500;

    public static function days(int $days): int
    {
        if ($days < 30 || $days > 3650) {
            throw new InvalidArgumentException('La rétention doit être comprise entre 30 et 3650 jours.');
        }

        return $days;
    }

    public static function batchSize(int $size): int
    {
        if ($size < 1 || $size > 5000) {
            throw new InvalidArgumentException('La taille de lot doit être comprise entre 1 et 5000.');
        }

        return $size;
    }
}
