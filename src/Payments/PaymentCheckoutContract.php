<?php

declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

final class PaymentCheckoutContract
{
    private const MIN_SESSION_LIFETIME_SECONDS = 30 * 60;

    /** @param array<string,mixed> $draft @param array<string,mixed> $attempt */
    public static function assertCompatible(array $draft, array $attempt, int $userId): void
    {
        $draftId = (int) ($draft['draft_id'] ?? 0);
        $attemptDraftId = (int) ($attempt['draft_id'] ?? 0);
        $draftUserId = (int) ($draft['utilisateur_id'] ?? 0);
        $expectedTotal = (int) ($draft['expected_total_cents'] ?? 0);
        $attemptTotal = (int) ($attempt['expected_amount_cents'] ?? 0);
        $draftCurrency = strtolower((string) ($draft['currency'] ?? ''));
        $attemptCurrency = strtolower((string) ($attempt['currency'] ?? ''));

        if ($draftId <= 0 || $attemptDraftId !== $draftId) {
            throw new RuntimeException('Tentative de paiement non liée au draft.');
        }
        if ($draftUserId !== $userId) {
            throw new RuntimeException('Draft de paiement non lié à cet utilisateur.');
        }
        if ($expectedTotal <= 0 || $attemptTotal !== $expectedTotal) {
            throw new RuntimeException('Montant de tentative incohérent avec le draft.');
        }
        if (!preg_match('/^[a-z]{3}$/', $draftCurrency) || $attemptCurrency !== $draftCurrency) {
            throw new RuntimeException('Devise de tentative incohérente avec le draft.');
        }
    }

    public static function idempotencyKey(int $attemptId, string $operation): string
    {
        $operation = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($operation)) ?? '';
        $operation = trim($operation, '-');

        if ($attemptId <= 0 || $operation === '') {
            throw new RuntimeException('Clé idempotente de paiement invalide.');
        }

        return 'tugeres-attempt-' . $attemptId . '-' . $operation;
    }

    public static function sessionExpiresAt(string $draftExpiresAt, ?int $now = null): int
    {
        $now ??= time();
        $expiresAt = strtotime($draftExpiresAt);

        if ($expiresAt === false || $expiresAt - $now < self::MIN_SESSION_LIFETIME_SECONDS) {
            throw new RuntimeException('Draft trop proche de son expiration pour démarrer le paiement.');
        }

        return $expiresAt;
    }
}
