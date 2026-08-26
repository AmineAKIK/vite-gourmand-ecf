<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class PaymentGatewayArchitectureContractTest extends TestCase
{
    public function testPaymentGatewayPortAndStripeAdapterAreExplicit(): void
    {
        $port = file_get_contents(dirname(__DIR__, 3) . '/src/Payments/PaymentGateway.php');
        $adapter = file_get_contents(dirname(__DIR__, 3) . '/src/Payments/StripePaymentGateway.php');
        self::assertIsString($port);
        self::assertIsString($adapter);

        self::assertStringContainsString('interface PaymentGateway', $port);
        self::assertStringContainsString('implements PaymentGateway', $adapter);
        self::assertStringContainsString('StripeClient', $adapter);
    }

    public function testCheckoutControllerContainsNoDirectStripeSdkCalls(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controllers/StripeController.php');
        self::assertIsString($controller);

        foreach (['\\Stripe\\', 'Stripe::setApiKey', 'Coupon::create', 'Checkout\\Session::create', 'Checkout\\Session::retrieve'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller, $forbidden);
        }
        self::assertStringContainsString('PaymentGatewayFactory::forProvider', $controller);
        self::assertStringContainsString('PaymentCheckoutRequest', $controller);
    }

    public function testOrderCreationPersistsProviderFromPaymentPolicy(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controllers/CommandeController.php');
        $attemptModel = file_get_contents(dirname(__DIR__, 3) . '/src/Models/PaymentAttemptModel.php');
        self::assertIsString($controller);
        self::assertIsString($attemptModel);

        self::assertStringContainsString("$provider = strtolower(trim((string) ($paymentMethod['provider'] ?? '')));", $controller);
        self::assertStringContainsString('PaymentGatewayFactory::supports($provider)', $controller);
        self::assertStringContainsString('$provider,', $attemptModel);
        self::assertStringNotContainsString("VALUES (?, 'stripe',", $attemptModel);
        self::assertStringNotContainsString("$attemptStmt->execute([$draftId, 'stripe'", $attemptModel);
    }

    public function testStripeCheckoutContractIsOnlyCompatibilityFacade(): void
    {
        $legacy = file_get_contents(dirname(__DIR__, 3) . '/src/Domain/StripeCheckoutContract.php');
        self::assertIsString($legacy);

        self::assertStringContainsString('PaymentCheckoutContract::assertCompatible', $legacy);
        self::assertStringContainsString('PaymentCheckoutContract::idempotencyKey', $legacy);
        self::assertStringContainsString('PaymentCheckoutContract::sessionExpiresAt', $legacy);
    }
}
