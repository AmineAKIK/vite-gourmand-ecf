<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigurationRegistry;
use App\Config\ConfigurationScope;
use App\Config\ConfigurationSource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigurationRegistryTest extends TestCase
{
    public function testMarketProfileIsFixedAndCanonical(): void
    {
        $expected = [
            'market.country' => 'FR',
            'market.currency' => 'EUR',
            'market.locale' => 'fr-FR',
            'market.timezone' => 'Europe/Paris',
        ];

        foreach ($expected as $key => $value) {
            $definition = ConfigurationRegistry::get($key);
            self::assertSame(ConfigurationScope::MARKET, $definition->scope, $key);
            self::assertSame(ConfigurationSource::FIXED, $definition->source, $key);
            self::assertSame($value, $definition->defaultValue, $key);
            self::assertNull($definition->storageKey, $key);
        }
    }

    public function testTenantConfigurationUsesSiteConfigAndAdminAuthority(): void
    {
        foreach (ConfigurationRegistry::forScope(ConfigurationScope::TENANT) as $key => $definition) {
            self::assertSame(ConfigurationSource::SITE_CONFIG, $definition->source, $key);
            self::assertSame('administrateur', $definition->editableRole, $key);
            self::assertFalse($definition->sensitive, $key);
            self::assertNotNull($definition->storageKey, $key);
        }
    }

    public function testOperatorConfigurationUsesEnvironmentAndKeepsSecretsSensitive(): void
    {
        foreach (ConfigurationRegistry::forScope(ConfigurationScope::OPERATOR) as $key => $definition) {
            self::assertSame(ConfigurationSource::ENV, $definition->source, $key);
            self::assertNull($definition->editableRole, $key);
        }

        foreach ([
            'operator.database.password',
            'operator.stripe.secret_key',
            'operator.stripe.webhook_secret',
            'operator.mail.brevo_api_key',
            'operator.cron.token',
        ] as $key) {
            $definition = ConfigurationRegistry::get($key);
            self::assertTrue($definition->sensitive, $key);
            self::assertSame(ConfigurationScope::OPERATOR, $definition->scope, $key);
        }

        self::assertSame('CRON_SECRET_TOKEN', ConfigurationRegistry::get('operator.cron.token')->storageKey);
    }

    public function testLegacyCommercialEntitlementsAndSecretsAreNotTenantStorageKeys(): void
    {
        $storageKeys = array_map(
            static fn($definition): ?string => $definition->storageKey,
            ConfigurationRegistry::siteConfigDefinitions(),
        );

        self::assertNotContains('plan', $storageKeys);
        self::assertNotContains('plan_suspendu', $storageKeys);
        self::assertNotContains('cron_secret_token', $storageKeys);
        self::assertNotContains('license_key', $storageKeys);
        self::assertNotContains('license_hash', $storageKeys);
    }

    public function testDefinitionsNormalizeValuesAccordingToTheirDeclaredType(): void
    {
        self::assertSame(42, ConfigurationRegistry::get('delivery.radius_km')->normalize('42'));
        self::assertSame('4.75', ConfigurationRegistry::get('delivery.base_fee')->normalize('4.75'));
        self::assertSame('#AABBCC', ConfigurationRegistry::get('theme.primary_color')->normalize('#aabbcc'));
        self::assertSame('12345678901234', ConfigurationRegistry::get('business.siret')->normalize('123 456 789 01234'));
        self::assertSame(
            ['33000', '33100'],
            ConfigurationRegistry::get('delivery.free_postal_codes')->normalize('33000, 33100,33000'),
        );
        self::assertSame(
            'non_assujetti',
            ConfigurationRegistry::get('tax.regime')->normalize('non_assujetti'),
        );
    }

    public function testInvalidTypedValuesFailClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfigurationRegistry::get('delivery.radius_km')->normalize('0');
    }
}
