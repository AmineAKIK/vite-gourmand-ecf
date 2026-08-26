<?php

declare(strict_types=1);

namespace App\Payments;

interface PaymentWebhookGateway
{
    public function provider(): string;

    public function parse(string $payload, string $signature): PaymentProviderEvent;
}
