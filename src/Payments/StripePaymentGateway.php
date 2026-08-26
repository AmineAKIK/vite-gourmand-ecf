<?php

declare(strict_types=1);

namespace App\Payments;

use App\Config\OperatorConfiguration;
use RuntimeException;
use Stripe\StripeClient;

final class StripePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly StripeClient $client)
    {
    }

    public static function fromConfiguration(): self
    {
        $secret = OperatorConfiguration::string('operator.stripe.secret_key');
        if ($secret === '' || str_contains($secret, 'REMPLACER')) {
            throw new RuntimeException('Configuration Stripe indisponible.');
        }

        return new self(new StripeClient($secret));
    }

    public function provider(): string
    {
        return 'stripe';
    }

    public function createCheckout(PaymentCheckoutRequest $request): PaymentCheckoutSession
    {
        $currency = $request->normalizedCurrency();
        $lineItems = [];
        foreach ($request->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $item['amount_cents'],
                    'product_data' => ['name' => $item['name']],
                ],
                'quantity' => 1,
            ];
        }

        $discounts = [];
        if ($request->discountCents > 0) {
            $coupon = $this->client->coupons->create([
                'amount_off' => $request->discountCents,
                'currency' => $currency,
                'duration' => 'once',
                'name' => 'Réduction commande',
            ], [
                'idempotency_key' => PaymentCheckoutContract::idempotencyKey($request->attemptId, 'coupon'),
            ]);
            $discounts[] = ['coupon' => $coupon->id];
        }

        $metadata = [
            'draft_id' => (string) $request->draftId,
            'attempt_id' => (string) $request->attemptId,
            'numero_commande' => $request->orderReference,
            'utilisateur_id' => (string) $request->userId,
            'expected_total_cents' => (string) $request->expectedAmountCents,
            'currency' => $currency,
        ];

        $session = $this->client->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'discounts' => $discounts,
            'mode' => 'payment',
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'metadata' => $metadata,
            'client_reference_id' => $request->orderReference,
            'expires_at' => $request->expiresAt,
        ], [
            'idempotency_key' => PaymentCheckoutContract::idempotencyKey($request->attemptId, 'checkout-session'),
        ]);

        return $this->mapSession($session);
    }

    public function retrieveCheckout(string $providerSessionId): PaymentCheckoutSession
    {
        $providerSessionId = trim($providerSessionId);
        if ($providerSessionId === '' || strlen($providerSessionId) > 255) {
            throw new RuntimeException('Référence de session paiement invalide.');
        }

        return $this->mapSession($this->client->checkout->sessions->retrieve($providerSessionId));
    }

    private function mapSession(object $session): PaymentCheckoutSession
    {
        $metadata = [];
        foreach (['draft_id', 'attempt_id', 'numero_commande', 'utilisateur_id', 'expected_total_cents', 'currency'] as $key) {
            if (isset($session->metadata->{$key})) {
                $metadata[$key] = (string) $session->metadata->{$key};
            }
        }

        return new PaymentCheckoutSession(
            provider: $this->provider(),
            id: (string) ($session->id ?? ''),
            status: (string) ($session->status ?? ''),
            url: isset($session->url) ? (string) $session->url : null,
            paymentStatus: isset($session->payment_status) ? (string) $session->payment_status : null,
            amountTotalCents: isset($session->amount_total) ? (int) $session->amount_total : null,
            currency: isset($session->currency) ? strtolower((string) $session->currency) : null,
            paymentIntentId: isset($session->payment_intent) ? (string) $session->payment_intent : null,
            metadata: $metadata,
        );
    }
}
