<?php

namespace App\Domain;

use DateTimeImmutable;

final class ReminderLeasePolicy
{
    public static function canClaim(?string $sentAt, ?string $leaseUntil, DateTimeImmutable $now): bool
    {
        if ($sentAt !== null) {
            return false;
        }

        if ($leaseUntil === null) {
            return true;
        }

        $until = new DateTimeImmutable($leaseUntil);

        return $until <= $now;
    }

    public static function errorMessage(\Throwable $error): string
    {
        $message = trim($error->getMessage());
        if ($message === '') {
            $message = $error::class;
        }

        return mb_substr($message, 0, 500);
    }
}
