<?php

declare(strict_types=1);

namespace App\Payments;

use App\Config\OperatorConfiguration;
use RuntimeException;
use Stripe\StripeClient;

final class StripePaymentRefundGateway implements PaymentRefundGateway
{
    public function __construct(private readonly StripeClient $client) {}

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

    public function refund(
        string $providerPaymentReference,
        int $amountCents,
        string $idempotencyKey,
        array $metadata = [],
    ): PaymentRefundResult {
        $providerPaymentReference = trim($providerPaymentReference);
        if ($providerPaymentReference === '' || $amountCents <= 0 || trim($idempotencyKey) === '') {
            throw new RuntimeException('Demande de remboursement fournisseur invalide.');
        }

        if (str_starts_with($providerPaymentReference, 'cs_')) {
            $session = $this->client->checkout->sessions->retrieve($providerPaymentReference);
            $providerPaymentReference = (string) ($session->payment_intent ?? '');
        }
        if (!str_starts_with($providerPaymentReference, 'pi_')) {
            throw new RuntimeException('Référence de paiement Stripe introuvable.');
        }

        $refund = $this->client->refunds->create([
            'payment_intent' => $providerPaymentReference,
            'amount' => $amountCents,
            'metadata' => $metadata,
        ], ['idempotency_key' => $idempotencyKey]);

        $status = (string) ($refund->status ?? 'pending');
        if ($status === 'canceled') {
            $status = 'failed';
        }
        if (!in_array($status, ['pending', 'succeeded', 'failed'], true)) {
            $status = 'pending';
        }

        return new PaymentRefundResult((string) $refund->id, $status);
    }
}
