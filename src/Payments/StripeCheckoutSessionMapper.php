<?php

declare(strict_types=1);

namespace App\Payments;

final class StripeCheckoutSessionMapper
{
    public static function map(object $session): PaymentCheckoutSession
    {
        $metadata = [];
        foreach (['draft_id', 'attempt_id', 'numero_commande', 'utilisateur_id', 'expected_total_cents', 'currency'] as $key) {
            if (isset($session->metadata->{$key})) {
                $metadata[$key] = (string) $session->metadata->{$key};
            }
        }

        return new PaymentCheckoutSession(
            provider: 'stripe',
            id: (string) ($session->id ?? ''),
            status: (string) ($session->status ?? ''),
            url: isset($session->url) ? (string) $session->url : null,
            paymentStatus: isset($session->payment_status) ? (string) $session->payment_status : null,
            amountTotalCents: isset($session->amount_total) ? (int) $session->amount_total : null,
            currency: isset($session->currency) ? strtolower((string) $session->currency) : null,
            paymentIntentId: isset($session->payment_intent) ? (string) $session->payment_intent : null,
            clientReferenceId: isset($session->client_reference_id) ? (string) $session->client_reference_id : null,
            metadata: $metadata,
        );
    }
}
