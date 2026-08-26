<?php

declare(strict_types=1);

namespace App\Payments;

final class PaymentCheckoutSession
{
    /** @param array<string,string> $metadata */
    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $url = null,
        public readonly ?string $paymentStatus = null,
        public readonly ?int $amountTotalCents = null,
        public readonly ?string $currency = null,
        public readonly ?string $paymentIntentId = null,
        public readonly array $metadata = [],
    ) {
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }
}
