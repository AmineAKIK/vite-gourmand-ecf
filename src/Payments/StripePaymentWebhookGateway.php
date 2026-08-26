<?php

declare(strict_types=1);

namespace App\Payments;

use App\Config\OperatorConfiguration;
use RuntimeException;
use Stripe\Webhook;

final class StripePaymentWebhookGateway implements PaymentWebhookGateway
{
    public function __construct(private readonly string $webhookSecret) {}

    public static function fromConfiguration(): self
    {
        $secret = OperatorConfiguration::string('operator.stripe.webhook_secret');
        if ($secret === '' || str_contains($secret, 'REMPLACER')) {
            throw new RuntimeException('Configuration webhook Stripe indisponible.');
        }

        return new self($secret);
    }

    public function provider(): string
    {
        return 'stripe';
    }

    public function parse(string $payload, string $signature): PaymentProviderEvent
    {
        if ($payload === '' || trim($signature) === '') {
            throw new RuntimeException('Webhook de paiement incomplet.');
        }

        $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        $eventId = (string) ($event->id ?? '');
        $eventType = (string) ($event->type ?? '');
        $occurredAt = isset($event->created) ? (int) $event->created : null;
        $object = $event->data->object ?? null;
        $objectId = is_object($object) && isset($object->id) ? (string) $object->id : null;

        return match ($eventType) {
            'checkout.session.completed' => new PaymentProviderEvent(
                provider: $this->provider(),
                id: $eventId,
                providerType: $eventType,
                kind: PaymentProviderEvent::CHECKOUT_PAID,
                objectId: $objectId,
                occurredAt: $occurredAt,
                checkout: is_object($object) ? StripeCheckoutSessionMapper::map($object) : null,
            ),
            'checkout.session.expired' => new PaymentProviderEvent(
                provider: $this->provider(),
                id: $eventId,
                providerType: $eventType,
                kind: PaymentProviderEvent::CHECKOUT_EXPIRED,
                objectId: $objectId,
                occurredAt: $occurredAt,
                checkout: is_object($object) ? StripeCheckoutSessionMapper::map($object) : null,
            ),
            'payment_intent.payment_failed' => new PaymentProviderEvent(
                provider: $this->provider(),
                id: $eventId,
                providerType: $eventType,
                kind: PaymentProviderEvent::PAYMENT_FAILED,
                objectId: $objectId,
                occurredAt: $occurredAt,
                paymentIntentId: $objectId,
            ),
            default => new PaymentProviderEvent(
                provider: $this->provider(),
                id: $eventId,
                providerType: $eventType !== '' ? $eventType : 'unknown',
                kind: PaymentProviderEvent::IGNORED,
                objectId: $objectId,
                occurredAt: $occurredAt,
            ),
        };
    }
}
