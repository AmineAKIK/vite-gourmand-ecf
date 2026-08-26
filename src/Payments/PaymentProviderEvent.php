<?php

declare(strict_types=1);

namespace App\Payments;

use InvalidArgumentException;

final class PaymentProviderEvent
{
    public const CHECKOUT_PAID = 'checkout_paid';
    public const CHECKOUT_EXPIRED = 'checkout_expired';
    public const PAYMENT_FAILED = 'payment_failed';
    public const IGNORED = 'ignored';

    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly string $providerType,
        public readonly string $kind,
        public readonly ?string $objectId = null,
        public readonly ?int $occurredAt = null,
        public readonly ?PaymentCheckoutSession $checkout = null,
        public readonly ?string $paymentIntentId = null,
    ) {
        if (trim($this->provider) === '' || trim($this->id) === '' || trim($this->providerType) === '') {
            throw new InvalidArgumentException('Événement de paiement incomplet.');
        }
        if (!in_array($this->kind, [
            self::CHECKOUT_PAID,
            self::CHECKOUT_EXPIRED,
            self::PAYMENT_FAILED,
            self::IGNORED,
        ], true)) {
            throw new InvalidArgumentException('Type canonique d’événement paiement invalide.');
        }
        if ($this->kind === self::CHECKOUT_PAID && $this->checkout === null) {
            throw new InvalidArgumentException('Un paiement checkout confirmé doit porter sa session.');
        }
    }
}
