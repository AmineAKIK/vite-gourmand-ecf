<?php

namespace Tests\Unit\Config;

use App\Config\ConfigurationRegistry;
use App\Config\ConfigurationScope;
use App\Config\ConfigurationSource;
use App\Config\ConfigurationType;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

final class ConfigurationRegistryTest extends TestCase
{
    public function testCanonicalTenantKeysMapToLegacyStorageWithoutLeakingItToConsumers(): void
    {
        $brand = ConfigurationRegistry::get('brand.name');
        self::assertSame(ConfigurationScope::TENANT, $brand->scope);
        self::assertSame(ConfigurationType::STRING, $brand->type);
        self::assertSame(ConfigurationSource::SITE_CONFIG, $brand->source);
        self::assertSame('site_nom', $brand->storageKey);
        self::assertTrue($brand->required);

        $delivery = ConfigurationRegistry::get('delivery.radius_km');
        self::assertSame('livraison_rayon_max_km', $delivery->storageKey);
        self::assertFalse($delivery->hasDefault());
    }

    public function testMarketProfileIsFixedAndExplicit(): void
    {
        $currency = ConfigurationRegistry::get('market.currency');
        self::assertSame(ConfigurationScope::MARKET, $currency->scope);
        self::assertSame(ConfigurationSource::FIXED, $currency->source);
        self::assertSame('EUR', $currency->defaultValue);
        self::assertNull($currency->storageKey);
        self::assertNull($currency->editableRole);

        self::assertSame('fr-FR', ConfigurationRegistry::get('market.locale')->defaultValue);
        self::assertSame('Europe/Paris', ConfigurationRegistry::get('market.timezone')->defaultValue);
    }

    public function testCommercialPoliciesHaveNoSilentFallbacks(): void
    {
        foreach ([
            'delivery.radius_km',
            'delivery.base_fee',
            'delivery.per_km_fee',
            'order.capacity.max_per_day',
            'discount.threshold',
            'discount.rate_percent',
            'payment.deposit.default_rate_percent',
            'payment.terms_days',
        ] as $key) {
            self::assertFalse(ConfigurationRegistry::get($key)->hasDefault(), $key);
        }
    }

    public function testSecretsAreOperatorEnvironmentConfigurationOnly(): void
    {
        foreach ([
            'operator.database.password',
            'operator.stripe.secret_key',
            'operator.stripe.webhook_secret',
            'operator.mail.brevo_api_key',
            'operator.cron.token',
        ] as $key) {
            $definition = ConfigurationRegistry::get($key);
            self::assertSame(ConfigurationScope::OPERATOR, $definition->scope, $key);
            self::assertSame(ConfigurationSource::ENVIRONMENT, $definition->source, $key);
            self::assertTrue($definition->sensitive, $key);
            self::assertSame('operator', $definition->editableRole, $key);
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
        self::assertSame(4.75, ConfigurationRegistry::get('delivery.base_fee')->normalize('4.75'));
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

    public function testUnknownKeysFailClosed(): void
    {
        $this->expectException(OutOfBoundsException::class);
        ConfigurationRegistry::get('legacy.magic_fallback');
    }

    public function testStorageLookupResolvesTheCanonicalDefinition(): void
    {
        $definition = ConfigurationRegistry::byStorageKey(
            ConfigurationSource::SITE_CONFIG,
            'entreprise_nom',
        );

        self::assertSame('business.legal_name', $definition->key);
    }
}
