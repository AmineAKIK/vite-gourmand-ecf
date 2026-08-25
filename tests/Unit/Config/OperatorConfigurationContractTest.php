<?php

namespace Tests\Unit\Config;

use App\Config\ConfigurationInvalidException;
use App\Config\OperatorConfiguration;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class OperatorConfigurationContractTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET', 'APP_ENV'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function testOperatorValueIsResolvedThroughTypedRegistry(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_contract');

        self::assertSame(
            'sk_test_contract',
            OperatorConfiguration::string('operator.stripe.secret_key'),
        );
        self::assertTrue(OperatorConfiguration::isConfigured('operator.stripe.secret_key'));
    }

    public function testOperatorDefaultIsNormalizedByRegistry(): void
    {
        putenv('APP_ENV');

        self::assertSame('production', OperatorConfiguration::string('operator.app_env'));
    }

    public function testInvalidOperatorEnumFailsClosed(): void
    {
        putenv('APP_ENV=banana');
        $this->expectException(ConfigurationInvalidException::class);

        OperatorConfiguration::get('operator.app_env');
    }

    public function testTenantKeyCannotBeReadAsOperatorConfiguration(): void
    {
        $this->expectException(UnexpectedValueException::class);

        OperatorConfiguration::get('tenant.identity.name');
    }

    public function testStripeConsumersDoNotReadBootstrapAliasesDirectly(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            $root . '/src/Controllers/StripeController.php',
            $root . '/src/Controllers/StripeFulfillmentController.php',
        ] as $path) {
            $source = (string) file_get_contents($path);
            self::assertStringContainsString('OperatorConfiguration::', $source);
            self::assertStringNotContainsString('STRIPE_SECRET_KEY', $source);
            self::assertStringNotContainsString('STRIPE_WEBHOOK_SECRET', $source);
            self::assertStringNotContainsString('BASE_URL', $source);
        }
    }

    public function testLegacyStripeFulfillmentFallbackIsRemoved(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/StripeFulfillmentController.php',
        );

        self::assertStringNotContainsString('processLegacyCompletedSession', $source);
        self::assertStringNotContainsString('Paiement Stripe legacy via webhook', $source);
    }
}
