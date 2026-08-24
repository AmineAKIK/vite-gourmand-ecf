<?php

declare(strict_types=1);

namespace App\Domain;

final class PaymentState
{
    public const DRAFT_PENDING = 'pending_payment';
    public const DRAFT_PAID = 'paid';
    public const DRAFT_CANCELLED = 'cancelled';
    public const DRAFT_FAILED = 'failed';
    public const DRAFT_CONSUMED = 'consumed';

    public const ATTEMPT_CREATED = 'created';
    public const ATTEMPT_CHECKOUT_CREATED = 'checkout_created';
    public const ATTEMPT_PAID = 'paid';
    public const ATTEMPT_CANCELLED = 'cancelled';
    public const ATTEMPT_FAILED = 'failed';

    public static function isDraftStatus(string $status): bool
    {
        return in_array($status, [
            self::DRAFT_PENDING,
            self::DRAFT_PAID,
            self::DRAFT_CANCELLED,
            self::DRAFT_FAILED,
            self::DRAFT_CONSUMED,
        ], true);
    }

    public static function isAttemptStatus(string $status): bool
    {
        return in_array($status, [
            self::ATTEMPT_CREATED,
            self::ATTEMPT_CHECKOUT_CREATED,
            self::ATTEMPT_PAID,
            self::ATTEMPT_CANCELLED,
            self::ATTEMPT_FAILED,
        ], true);
    }

    public static function isDraftUsable(string $status): bool
    {
        return $status === self::DRAFT_PENDING;
    }
}
