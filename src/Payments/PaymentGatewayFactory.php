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
        return match (self::normalize($provider)) {
            'stripe' => StripePaymentGateway::fromConfiguration(),
            default => throw new InvalidArgumentException('Fournisseur de paiement non supporté.'),
        };
    }

    public static function webhookForProvider(string $provider): PaymentWebhookGateway
    {
        return match (self::normalize($provider)) {
            'stripe' => StripePaymentWebhookGateway::fromConfiguration(),
            default => throw new InvalidArgumentException('Webhook fournisseur non supporté.'),
        };
    }

    public static function refundForProvider(string $provider): PaymentRefundGateway
    {
        return match (self::normalize($provider)) {
            'stripe' => StripePaymentRefundGateway::fromConfiguration(),
            default => throw new InvalidArgumentException('Remboursement fournisseur non supporté.'),
        };
    }

    public static function checkoutPath(string $provider): string
    {
        return match (self::normalize($provider)) {
            'stripe' => '/stripe/checkout',
            default => throw new InvalidArgumentException('Fournisseur de paiement non supporté.'),
        };
    }

    public static function successUrl(string $provider, string $baseUrl): string
    {
        return match (self::normalize($provider)) {
            'stripe' => rtrim($baseUrl, '/') . '/stripe/success?session_id={CHECKOUT_SESSION_ID}',
            default => throw new InvalidArgumentException('Fournisseur de paiement non supporté.'),
        };
    }

    public static function cancelUrl(string $provider, string $baseUrl): string
    {
        return match (self::normalize($provider)) {
            'stripe' => rtrim($baseUrl, '/') . '/stripe/cancel',
            default => throw new InvalidArgumentException('Fournisseur de paiement non supporté.'),
        };
    }

    private static function normalize(string $provider): string
    {
        return trim(strtolower($provider));
    }
}
