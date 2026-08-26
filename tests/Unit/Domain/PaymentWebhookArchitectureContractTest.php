<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class PaymentWebhookArchitectureContractTest extends TestCase
{
    public function testFulfillmentControllerUsesProviderNeutralPortsAndPersistedSuccessState(): void
    {
        $source = $this->source('src/Controllers/StripeFulfillmentController.php');

        self::assertStringContainsString("PaymentGatewayFactory::webhookForProvider('stripe')", $source);
        self::assertStringContainsString('PaymentWebhookFulfillmentService::handle($event)', $source);
        self::assertStringContainsString('AdditionalProviderPaymentReconciliationService::recordIfNeeded', $source);
        self::assertStringContainsString("PaymentAttemptModel::findProviderContextForUser('stripe'", $source);
        self::assertStringContainsString('PaymentSuccessReconciliation::state(', $source);
        self::assertStringNotContainsString('\\Stripe\\', $source);
        self::assertStringNotContainsString('StripeWebhookContract', $source);
        self::assertStringNotContainsString('StripeWebhookFulfillmentService', $source);
        self::assertStringNotContainsString('Checkout\\Session::retrieve', $source);
        self::assertStringNotContainsString('OperatorConfiguration', $source);
    }

    public function testAdditionalPaidAttemptIsPersistedForFinancialReconciliation(): void
    {
        $source = $this->source('src/Services/AdditionalProviderPaymentReconciliationService.php');

        self::assertStringContainsString("SET status = 'paid'", $source);
        self::assertStringContainsString('provider_payment_intent_id = COALESCE', $source);
        self::assertStringContainsString('réconciliation financière requise', $source);
        self::assertStringNotContainsString('INSERT INTO paiement', $source);
        self::assertStringNotContainsString('INSERT INTO commande', $source);
    }

    public function testStripeAdapterPropagatesCanonicalMetadataToPaymentIntent(): void
    {
        $source = $this->source('src/Payments/StripePaymentGateway.php');

        self::assertStringContainsString("'payment_intent_data' => ['metadata' => \$metadata]", $source);
    }

    public function testProviderInboxMigrationIsForwardOnlyAndPreservesLegacyEvents(): void
    {
        $sql = $this->source('sql/v1/migrations/005_payment_provider_event_inbox.sql');

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS payment_provider_event', $sql);
        self::assertStringContainsString('PRIMARY KEY (provider, event_id)', $sql);
        self::assertStringContainsString('FROM stripe_webhook_event', $sql);
        self::assertStringNotContainsString('DROP TABLE', strtoupper($sql));
        self::assertStringNotContainsString('TRUNCATE', strtoupper($sql));
        self::assertStringNotContainsString('DELETE FROM stripe_webhook_event', $sql);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
        self::assertIsString($source);

        return $source;
    }
}
