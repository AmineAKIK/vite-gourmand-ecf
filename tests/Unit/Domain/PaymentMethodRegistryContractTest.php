<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Services\PaymentMethodRegistry;
use PHPUnit\Framework\TestCase;

final class PaymentMethodRegistryContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testV1CapabilitiesAreExplicitAndJourneyAware(): void
    {
        $capabilities = PaymentMethodRegistry::capabilities();

        self::assertSame(['virement', 'cheque', 'especes', 'cb_online'], array_keys($capabilities));
        self::assertNull($capabilities['virement']['provider']);
        self::assertTrue($capabilities['virement']['supports_manual_collection']);
        self::assertSame(PaymentMethodRegistry::CHECKOUT_STRATEGY_CREATE_ORDER, $capabilities['virement']['checkout_strategy']);

        self::assertSame('stripe', $capabilities['cb_online']['provider']);
        self::assertFalse($capabilities['cb_online']['supports_manual_collection']);
        self::assertSame(PaymentMethodRegistry::CHECKOUT_STRATEGY_PROVIDER_CONFIRMATION, $capabilities['cb_online']['checkout_strategy']);
        self::assertSame(PaymentMethodRegistry::ORDER_EFFECT_CONFIRM_AFTER_PROVIDER, $capabilities['cb_online']['order_status_effect']);
    }

    public function testCheckoutAndManualCollectionUseRegistryInsteadOfRawActiveFlag(): void
    {
        $commande = $this->source('src/Controllers/CommandeController.php');
        $panierController = $this->source('src/Controllers/PanierController.php');
        $panierView = $this->source('src/Views/pages/panier/index.php');
        $paiementController = $this->source('src/Controllers/PaiementController.php');
        $paiementModel = $this->source('src/Models/PaiementModel.php');

        self::assertStringContainsString('PaymentMethodRegistry::requireCheckoutMethod($modePaiement)', $commande);
        self::assertStringContainsString('checkout_strategy', $commande);
        self::assertStringNotContainsString('SELECT code FROM mode_paiement', $commande);
        self::assertStringNotContainsString('$modePaiement === \'cb_online\'', $commande);

        self::assertStringContainsString('PaymentMethodRegistry::checkoutMethods()', $panierController);
        self::assertStringNotContainsString('SELECT * FROM mode_paiement', $panierView);
        self::assertStringNotContainsString('db()', $panierView);
        self::assertStringContainsString('$paymentMethods', $panierView);

        self::assertStringContainsString('PaymentMethodRegistry::requireManualCollectionMethod', $paiementController);
        self::assertStringContainsString('PaymentMethodRegistry::manualCollectionMethods()', $paiementModel);
    }

    public function testChosenMethodIsPersistedAsImmutableOrderSnapshotInBothCreationPaths(): void
    {
        $commande = $this->source('src/Controllers/CommandeController.php');
        $model = $this->source('src/Models/CommandeModel.php');
        $stripe = $this->source('src/Services/StripeWebhookFulfillmentService.php');
        $migration = $this->source('sql/v1/migrations/003_payment_method_registry.sql');

        self::assertStringContainsString("'payment_method_code'", $commande);
        self::assertStringContainsString('payment_method_code, instructions', $model);
        self::assertStringContainsString("['payment_method_code']", $model);

        self::assertStringContainsString("['payment_method_code'] ?? '') !== 'cb_online'", $stripe);
        self::assertStringContainsString('payment_method_code, instructions', $stripe);

        self::assertStringContainsString('ADD COLUMN payment_method_code VARCHAR(30) NULL', $migration);
        self::assertStringNotContainsString('FOREIGN KEY (payment_method_code)', $migration);
    }

    public function testMigrationAddsNoTenantCommercialSeedAndPinsDatabaseInvariants(): void
    {
        $migration = $this->source('sql/v1/migrations/003_payment_method_registry.sql');
        $verifier = $this->source('bin/verify-v1-schema.php');

        self::assertStringNotContainsString('INSERT INTO mode_paiement', $migration);
        self::assertStringContainsString('chk_mode_paiement_flags', $migration);
        self::assertStringContainsString('chk_mode_paiement_cb_online', $migration);
        self::assertStringContainsString('chk_mode_paiement_instructions', $migration);
        self::assertStringContainsString("code <> 'cb_online'", $migration);

        self::assertStringContainsString("'payment_method_code'", $verifier);
        self::assertStringContainsString("'checkout_enabled'", $verifier);
        self::assertStringContainsString('chk_mode_paiement_cb_online', $verifier);
    }

    public function testStripeConfigurationIsScopedToStripeCapabilityOnly(): void
    {
        $completeness = $this->source('src/Config/ConfigurationCompleteness.php');
        $registry = $this->source('src/Services/PaymentMethodRegistry.php');
        $stripeController = $this->source('src/Controllers/StripeController.php');

        $checkoutBlockStart = strpos($completeness, "'checkout' => [");
        $billingBlockStart = strpos($completeness, "'billing' => [");
        self::assertNotFalse($checkoutBlockStart);
        self::assertNotFalse($billingBlockStart);
        $checkoutBlock = substr($completeness, (int) $checkoutBlockStart, (int) $billingBlockStart - (int) $checkoutBlockStart);

        self::assertStringNotContainsString('operator.stripe.secret_key', $checkoutBlock);
        self::assertStringContainsString("'operator.stripe.secret_key'", $registry);
        self::assertStringContainsString("'operator.stripe.webhook_secret'", $registry);
        self::assertStringContainsString("PaymentMethodRegistry::requireCheckoutMethod('cb_online')", $stripeController);
    }

    public function testAdminWritesAllSupportedPoliciesAtomically(): void
    {
        $controller = $this->source('src/Controllers/Admin/ParametresController.php');
        $view = $this->source('src/Views/pages/admin/payment_methods.php');

        self::assertStringContainsString('$db->beginTransaction()', $controller);
        self::assertStringContainsString('array_keys(PaymentMethodRegistry::capabilities())', $controller);
        self::assertStringContainsString('PaymentMethodRegistry::saveTenantPolicy', $controller);
        self::assertStringContainsString('$db->rollBack()', $controller);
        self::assertStringContainsString('payment_methods[', $view);
        self::assertStringNotContainsString('STRIPE_SECRET_KEY', $view);
        self::assertStringNotContainsString('STRIPE_WEBHOOK_SECRET', $view);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, 'Unable to read ' . $path);
        return $source;
    }
}
