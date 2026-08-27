<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class FinancialCancellationArchitectureContractTest extends TestCase
{
    public function testOrderCancellationUsesProviderRefundPortAndNoDirectStripeApi(): void
    {
        $source = $this->source('src/Services/OrderCancellationService.php');

        self::assertStringContainsString('PaymentGatewayFactory::refundForProvider($provider)', $source);
        self::assertStringContainsString('cancellationRefundPercent', $source);
        self::assertStringContainsString('provider-attempt-refund:', $source);
        self::assertStringContainsString("pa.status = 'paid'", $source);
        self::assertStringContainsString("'refunded'", $source);
        self::assertStringNotContainsString('\\Stripe\\', $source);
        self::assertStringNotContainsString('STRIPE_SECRET_KEY', $source);
        self::assertStringNotContainsString('Stripe::setApiKey', $source);
    }

    public function testStripeRefundImplementationIsConfinedToAdapter(): void
    {
        $source = $this->source('src/Payments/StripePaymentRefundGateway.php');

        self::assertStringContainsString('implements PaymentRefundGateway', $source);
        self::assertStringContainsString("OperatorConfiguration::string('operator.stripe.secret_key')", $source);
        self::assertStringContainsString("'idempotency_key' => \$idempotencyKey", $source);
    }

    public function testMigrationPersistsAdditionalProviderRefundOutcomeWithoutDeletingData(): void
    {
        $sql = $this->source('sql/v1/migrations/006_financial_cancellation_refunds.sql');

        self::assertStringContainsString('provider_refund_id', $sql);
        self::assertStringContainsString('refunded_at', $sql);
        self::assertStringContainsString('information_schema.COLUMNS', $sql);
        self::assertStringContainsString('PREPARE add_provider_refund_id_stmt', $sql);
        self::assertStringContainsString('PREPARE add_refunded_at_stmt', $sql);
        self::assertStringNotContainsString('ADD COLUMN IF NOT EXISTS', $sql);
        self::assertStringNotContainsString('DROP TABLE', strtoupper($sql));
        self::assertStringNotContainsString('TRUNCATE', strtoupper($sql));
        self::assertStringNotContainsString('DELETE FROM', strtoupper($sql));
    }

    public function testTermsUseSameCancellationPolicy(): void
    {
        $terms = $this->source('src/Services/TermsAndConditionsService.php');

        self::assertStringContainsString('customerCancellationCutoffHours()', $terms);
        self::assertStringContainsString('remboursement intégral', $terms);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
        self::assertIsString($source);

        return $source;
    }
}
