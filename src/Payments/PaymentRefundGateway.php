<?php

declare(strict_types=1);

namespace App\Payments;

interface PaymentRefundGateway
{
    public function provider(): string;

    /** @param array<string,string> $metadata */
    public function refund(
        string $providerPaymentReference,
        int $amountCents,
        string $idempotencyKey,
        array $metadata = [],
    ): PaymentRefundResult;
}
