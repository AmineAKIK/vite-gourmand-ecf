<?php

declare(strict_types=1);

namespace App\Payments;

use InvalidArgumentException;

final class PaymentGatewayFactory
{
    public static function supports(string $provider): bool
    {
        return trim(strtolower($provider)) === 'stripe';
    }

    public static function forProvider(string $provider): PaymentGateway
    {
        return match (trim(strtolower($provider))) {
            'stripe' => StripePaymentGateway::fromConfiguration(),
            default => throw new InvalidArgumentException('Fournisseur de paiement non supporté.'),
        };
    }

    public static function checkoutPath(string $provider): string
    {
        return match (trim(strtolower($provider))) {
            'stripe' => '/stripe/checkout',
            default => throw new InvalidArgumentException('Fournisseur de paiement non supporté.'),
        };
    }
}
