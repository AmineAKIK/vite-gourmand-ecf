<?php

declare(strict_types=1);

namespace App\Payments;

final class PaymentSuccessReconciliation
{
    public const CONFIRMED = 'confirmed';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const INCONSISTENT = 'inconsistent';

    /** @param array<string,mixed> $draft @param array<string,mixed> $attempt */
    public static function state(array $draft, array $attempt): string
    {
        $draftStatus = (string) ($draft['status'] ?? '');
        $attemptStatus = (string) ($attempt['status'] ?? '');
        $commandeId = (int) ($draft['commande_id'] ?? 0);

        if ($draftStatus === 'consumed' && $commandeId > 0 && $attemptStatus === 'paid') {
            return self::CONFIRMED;
        }
        if ($draftStatus === 'pending_payment'
            && $commandeId === 0
            && in_array($attemptStatus, ['created', 'checkout_created'], true)) {
            return self::PENDING;
        }
        if (in_array($draftStatus, ['failed', 'cancelled'], true)
            || in_array($attemptStatus, ['failed', 'cancelled'], true)) {
            return self::FAILED;
        }

        return self::INCONSISTENT;
    }

    public static function shouldClearCart(array $currentCart, array $draftCart): bool
    {
        return self::normalize($currentCart) === self::normalize($draftCart);
    }

    private static function normalize(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}
