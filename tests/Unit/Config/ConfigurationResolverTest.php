<?php

namespace Tests\Unit\Config;

use App\Config\ConfigurationInvalidException;
use App\Config\ConfigurationMissingException;
use App\Config\ConfigurationResolver;
use App\Config\ConfigurationScope;
use PHPUnit\Framework\TestCase;

final class ConfigurationResolverTest extends TestCase
{
    public function testResolvesFixedMarketAndTypedTenantValues(): void
    {
        $resolver = new ConfigurationResolver([
            'livraison_rayon_max_km' => '35',
            'livraison_codes_postaux_gratuits' => '33000,33100,33000',
        ]);

        self::assertSame('EUR', $resolver->resolve('market.currency'));
        self::assertSame(35, $resolver->resolve('delivery.radius_km'));
        self::assertSame(
            ['33000', '33100'],
            $resolver->resolve('delivery.free_postal_codes'),
        );
    }

    public function testOptionalMissingValueUsesOnlyDeclaredDefault(): void
    {
        $resolver = new ConfigurationResolver([]);

        self::assertSame('#1F2937', $resolver->resolve('theme.primary_color'));
        self::assertSame('sobre', $resolver->resolve('quote.template'));
        self::assertNull($resolver->resolve('delivery.radius_km'));
    }

    public function testRequiredMissingValueFailsClosedEvenWhenOtherValuesExist(): void
    {
        $resolver = new ConfigurationResolver([
            'site_slogan' => 'Cuisine locale',
        ]);

        $this->expectException(ConfigurationMissingException::class);
        $resolver->resolve('brand.name');
    }

    public function testInvalidPersistedValueIsNotSilentlyReplaced(): void
    {
        $resolver = new ConfigurationResolver([
            'livraison_rayon_max_km' => '0',
        ]);

        $this->expectException(ConfigurationInvalidException::class);
        $resolver->resolve('delivery.radius_km');
    }

    public function testExplicitConfigurationIsSeparateFromEffectiveDefault(): void
    {
        $resolver = new ConfigurationResolver([]);

        self::assertSame('sobre', $resolver->resolve('quote.template'));
        self::assertFalse($resolver->isExplicitlyConfigured('quote.template'));
        self::assertTrue($resolver->isExplicitlyConfigured('market.locale'));
    }

    public function testMissingRequiredCanBeComputedByScopeForOnboarding(): void
    {
        $resolver = new ConfigurationResolver([
            'site_nom' => 'Maison Exemple',
            'site_email' => 'bonjour@example.test',
            'site_telephone' => '0102030405',
            'entreprise_nom' => 'Maison Exemple SAS',
            'entreprise_siret' => '12345678901234',
            'entreprise_adresse' => '1 rue Exemple',
            'entreprise_code_postal' => '75001',
            'entreprise_ville' => 'Paris',
            'entreprise_email' => 'admin@example.test',
        ]);

        self::assertSame(
            [
                'material.late_fee_cents',
                'material.return_days',
                'order.cancellation_cutoff_hours',
                'order.maximum_advance_days',
                'order.minimum_lead_hours',
                'order.number_prefix',
                'quote.validity_days',
                'reminder.order_days_before',
                'tax.regime',
            ],
            $resolver->missingRequired(ConfigurationScope::TENANT),
        );
    }

    public function testOperatorEnvironmentValuesUseTheSameTypedContract(): void
    {
        $resolver = new ConfigurationResolver([], [
            'APP_ENV' => 'production',
            'DB_HOST' => 'db.internal',
        ]);

        self::assertSame('production', $resolver->resolve('operator.app_env'));
        self::assertSame('db.internal', $resolver->resolve('operator.database.host'));
    }
}
