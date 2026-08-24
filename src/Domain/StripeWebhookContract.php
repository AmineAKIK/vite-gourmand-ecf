<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

final class StripeWebhookContract
{
    /**
     * @return array{draft_id:int, attempt_id:int, amount_total:int, currency:string, payment_intent:?string}
     */
    public static function assertPaidSession(array $session, array $draft, array $attempt): array
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $draftId = (int) ($metadata['draft_id'] ?? 0);
        $attemptId = (int) ($metadata['attempt_id'] ?? 0);
        $amountTotal = (int) ($session['amount_total'] ?? 0);
        $currency = strtolower((string) ($session['currency'] ?? ''));
        $paymentIntent = isset($session['payment_intent']) && $session['payment_intent'] !== ''
            ? (string) $session['payment_intent']
            : null;

        if ((string) ($session['payment_status'] ?? '') !== 'paid') {
            throw new RuntimeException('Checkout Session non payée.');
        }
        if ($draftId <= 0 || $attemptId <= 0) {
            throw new RuntimeException('Metadata de paiement incomplètes.');
        }
        if ($draftId !== (int) ($draft['draft_id'] ?? 0) || $attemptId !== (int) ($attempt['attempt_id'] ?? 0)) {
            throw new RuntimeException('Metadata draft/attempt incohérentes.');
        }
        if ((int) ($attempt['draft_id'] ?? 0) !== $draftId) {
            throw new RuntimeException('Tentative rattachée à un autre draft.');
        }
        if ((string) ($attempt['provider'] ?? '') !== 'stripe') {
            throw new RuntimeException('Provider de paiement inattendu.');
        }
        if ((string) ($attempt['provider_session_id'] ?? '') !== (string) ($session['id'] ?? '')) {
            throw new RuntimeException('Checkout Session non liée à la tentative.');
        }
        if ($amountTotal <= 0 || $amountTotal !== (int) ($draft['expected_total_cents'] ?? 0)) {
            throw new RuntimeException('Montant Stripe différent du montant attendu.');
        }
        if ($amountTotal !== (int) ($attempt['expected_amount_cents'] ?? 0)) {
            throw new RuntimeException('Montant Stripe différent de la tentative.');
        }
        if ($currency === '' || $currency !== strtolower((string) ($draft['currency'] ?? ''))) {
            throw new RuntimeException('Devise Stripe différente du draft.');
        }
        if ($currency !== strtolower((string) ($attempt['currency'] ?? ''))) {
            throw new RuntimeException('Devise Stripe différente de la tentative.');
        }
        if ((string) ($session['client_reference_id'] ?? '') !== (string) ($draft['numero_commande'] ?? '')) {
            throw new RuntimeException('Référence commande Stripe incohérente.');
        }
        if ((string) ($metadata['numero_commande'] ?? '') !== (string) ($draft['numero_commande'] ?? '')) {
            throw new RuntimeException('Metadata référence commande incohérente.');
        }
        if ((int) ($metadata['utilisateur_id'] ?? 0) !== (int) ($draft['utilisateur_id'] ?? 0)) {
            throw new RuntimeException('Metadata utilisateur incohérente.');
        }

        return [
            'draft_id' => $draftId,
            'attempt_id' => $attemptId,
            'amount_total' => $amountTotal,
            'currency' => $currency,
            'payment_intent' => $paymentIntent,
        ];
    }
}
