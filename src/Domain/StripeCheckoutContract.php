<?php

declare(strict_types=1);

namespace App\Domain;

use App\Payments\PaymentCheckoutContract;

/** @deprecated Use PaymentCheckoutContract for provider-neutral checkout invariants. */
final class StripeCheckoutContract
{
    /** @param array<string,mixed> $draft @param array<string,mixed> $attempt */
    public static function assertCompatible(array $draft, array $attempt, int $userId): void
    {
        PaymentCheckoutContract::assertCompatible($draft, $attempt, $userId);
    }

    public static function idempotencyKey(int $attemptId, string $operation): string
    {
        return PaymentCheckoutContract::idempotencyKey($attemptId, $operation);
    }

    public static function sessionExpiresAt(string $draftExpiresAt, ?int $now = null): int
    {
        return PaymentCheckoutContract::sessionExpiresAt($draftExpiresAt, $now);
    }
}
