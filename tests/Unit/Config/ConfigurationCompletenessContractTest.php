<?php

namespace Tests\Unit\Config;

use App\Config\ConfigurationCompleteness;
use App\Config\ConfigurationIncompleteException;
use PHPUnit\Framework\TestCase;

final class ConfigurationCompletenessContractTest extends TestCase
{
    public function testOrderingDoesNotDependOnStripe(): void
    {
        $ordering = ConfigurationCompleteness::keys('ordering');

        self::assertContains('delivery.base_fee', $ordering);
        self::assertContains('order.capacity.max_per_day', $ordering);
        self::assertContains('discount.rate_percent', $ordering);
        self::assertNotContains('operator.stripe.secret_key', $ordering);
    }

    public function testCheckoutRemainsProviderAgnostic(): void
    {
        $checkout = ConfigurationCompleteness::keys('checkout');

        self::assertContains('order.capacity.max_per_day', $checkout);
        self::assertNotContains('operator.stripe.secret_key', $checkout);
        self::assertNotContains('operator.stripe.webhook_secret', $checkout);
        self::assertNotContains('operator.base_url', $checkout);
    }

    public function testBillingRequiresLegalAndPaymentConfiguration(): void
    {
        $billing = ConfigurationCompleteness::keys('billing');

        self::assertContains('business.siret', $billing);
        self::assertContains('tax.regime', $billing);
        self::assertContains('payment.terms_days', $billing);
    }

    public function testIncompleteExceptionUsesStableMachinePrefix(): void
    {
        $error = new ConfigurationIncompleteException(['delivery.base_fee'], 'ordering');

        self::assertSame(['delivery.base_fee'], $error->keys());
        self::assertSame('configuration_incomplete:ordering:delivery.base_fee', $error->getMessage());
    }

    public function testCommercialFallbacksCannotReturnToLegacySiteConfig(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Config/SiteConfig.php');

        self::assertStringNotContainsString("livraison_rayon_max_km', 50", $source);
        self::assertStringNotContainsString("livraison_base', LIVRAISON_BASE", $source);
        self::assertStringNotContainsString("livraison_km', LIVRAISON_KM", $source);
        self::assertStringNotContainsString("reduction_seuil', '100.00'", $source);
        self::assertStringNotContainsString('REDUCTION_TAUX * 100', $source);
        self::assertStringNotContainsString("commandes_max_par_jour', 0", $source);
    }

    public function testFinancialFallbacksCannotReturnToPricingService(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Services/PricingService.php');

        self::assertStringContainsString('ConfigurationCompleteness::assertOrderingReady()', $source);
        self::assertStringNotContainsString('return self::isAssujetti() ? 10.0 : 0.0;', $source);
    }

    public function testBillingFinalizationRequiresCompleteConfiguration(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Services/BillingFinalizationService.php');

        self::assertStringContainsString('ConfigurationCompleteness::assertBillingReady()', $source);
    }
}
