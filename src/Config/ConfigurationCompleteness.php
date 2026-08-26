<?php

namespace App\Config;

final class ConfigurationCompleteness
{
    /** @var array<string,list<string>> */
    private const CONTEXTS = [
        'delivery' => [
            'contact.address.city',
            'delivery.origin.latitude',
            'delivery.origin.longitude',
            'delivery.radius_km',
            'delivery.base_fee',
            'delivery.per_km_fee',
        ],
        'ordering' => [
            'contact.address.city',
            'delivery.origin.latitude',
            'delivery.origin.longitude',
            'delivery.radius_km',
            'delivery.base_fee',
            'delivery.per_km_fee',
            'order.capacity.max_per_day',
            'order.number_prefix',
            'order.minimum_lead_hours',
            'order.maximum_advance_days',
            'order.cancellation_cutoff_hours',
            'quote.validity_days',
            'material.return_days',
            'material.late_fee_cents',
            'reminder.order_days_before',
            'discount.threshold',
            'discount.rate_percent',
        ],
        'checkout' => [
            'contact.address.city',
            'delivery.origin.latitude',
            'delivery.origin.longitude',
            'delivery.radius_km',
            'delivery.base_fee',
            'delivery.per_km_fee',
            'order.capacity.max_per_day',
            'order.number_prefix',
            'order.minimum_lead_hours',
            'order.maximum_advance_days',
            'order.cancellation_cutoff_hours',
            'quote.validity_days',
            'material.return_days',
            'material.late_fee_cents',
            'reminder.order_days_before',
            'discount.threshold',
            'discount.rate_percent',
            'operator.stripe.secret_key',
            'operator.base_url',
        ],
        'billing' => [
            'business.legal_name',
            'business.siret',
            'business.address.line1',
            'business.address.postal_code',
            'business.address.city',
            'business.email',
            'tax.regime',
            'payment.deposit.default_rate_percent',
            'payment.terms_days',
            'payment.late_fee_rate_percent',
            'payment.recovery_fee',
        ],
    ];

    public static function assertDeliveryReady(): void
    {
        self::assertReady('delivery');
    }

    public static function assertOrderingReady(): void
    {
        self::assertReady('ordering');
    }

    public static function assertCheckoutReady(): void
    {
        self::assertReady('checkout');
    }

    public static function assertBillingReady(): void
    {
        self::assertReady('billing');
    }

    /** @return list<string> */
    public static function keys(string $context): array
    {
        return self::CONTEXTS[$context] ?? [];
    }

    /** @return list<string> */
    public static function missing(string $context): array
    {
        $missing = [];

        foreach (self::keys($context) as $key) {
            if (!Configuration::isConfigured($key)) {
                $missing[] = $key;
                continue;
            }

            try {
                Configuration::get($key);
            } catch (ConfigurationMissingException|ConfigurationInvalidException) {
                $missing[] = $key;
            }
        }

        sort($missing);
        return $missing;
    }

    private static function assertReady(string $context): void
    {
        $missing = self::missing($context);
        if ($missing !== []) {
            throw new ConfigurationIncompleteException($missing, $context);
        }
    }
}
