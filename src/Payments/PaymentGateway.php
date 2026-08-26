<?php

declare(strict_types=1);

namespace App\Payments;

interface PaymentGateway
{
    public function provider(): string;

    public function createCheckout(PaymentCheckoutRequest $request): PaymentCheckoutSession;

    public function retrieveCheckout(string $providerSessionId): PaymentCheckoutSession;
}
