<?php

declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

final class PaymentReconciliationContract
{
    /**
     * @param array<string,mixed> $draft
     * @param array<string,mixed> $attempt
     * @return array{draft_id:int,attempt_id:int,amount_total:int,currency:string,payment_intent:?string}
     */
    public static function assertPaidCheckout(
        PaymentCheckoutSession $session,
        array $draft,
        array $attempt,
    ): array {
        $metadata = $session->metadata;
        $draftId = (int) ($metadata['draft_id'] ?? 0);
        $attemptId = (int) ($metadata['attempt_id'] ?? 0);
        $amountTotal = (int) ($session->amountTotalCents ?? 0);
        $currency = strtolower((string) ($session->currency ?? ''));
        $paymentIntent = $session->paymentIntentId !== null && $session->paymentIntentId !== ''
            ? $session->paymentIntentId
            : null;
        $expectedCents = (int) ($draft['expected_total_cents'] ?? 0);
        $draftCurrency = strtolower((string) ($draft['currency'] ?? ''));

        if ($session->paymentStatus !== 'paid') {
            throw new RuntimeException('Session de paiement non payée.');
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
        if ((string) ($attempt['provider'] ?? '') !== $session->provider) {
            throw new RuntimeException('Provider de paiement inattendu.');
        }
        if ((string) ($attempt['provider_session_id'] ?? '') !== $session->id) {
            throw new RuntimeException('Session fournisseur non liée à la tentative.');
        }
        if ($amountTotal <= 0 || $amountTotal !== $expectedCents) {
            throw new RuntimeException('Montant fournisseur différent du montant attendu.');
        }
        if ($amountTotal !== (int) ($attempt['expected_amount_cents'] ?? 0)) {
            throw new RuntimeException('Montant fournisseur différent de la tentative.');
        }
        if ((int) ($metadata['expected_total_cents'] ?? 0) !== $expectedCents) {
            throw new RuntimeException('Metadata montant attendu incohérente.');
        }
        if ($currency === '' || $currency !== $draftCurrency) {
            throw new RuntimeException('Devise fournisseur différente du draft.');
        }
        if ($currency !== strtolower((string) ($attempt['currency'] ?? ''))) {
            throw new RuntimeException('Devise fournisseur différente de la tentative.');
        }
        if (strtolower((string) ($metadata['currency'] ?? '')) !== $draftCurrency) {
            throw new RuntimeException('Metadata devise incohérente.');
        }
        if ((string) ($session->clientReferenceId ?? '') !== (string) ($draft['numero_commande'] ?? '')) {
            throw new RuntimeException('Référence commande fournisseur incohérente.');
        }
        if ((string) ($metadata['numero_commande'] ?? '') !== (string) ($draft['numero_commande'] ?? '')) {
            throw new RuntimeException('Metadata référence commande incohérente.');
        }
        if ((int) ($metadata['utilisateur_id'] ?? 0) !== (int) ($draft['utilisateur_id'] ?? 0)) {
            throw new RuntimeException('Metadata utilisateur incohérente.');
        }

        $knownPaymentIntent = (string) ($attempt['provider_payment_intent_id'] ?? '');
        if ($knownPaymentIntent !== '' && $paymentIntent !== $knownPaymentIntent) {
            throw new RuntimeException('Référence de paiement fournisseur incohérente.');
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
