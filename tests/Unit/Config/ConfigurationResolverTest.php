<?php

namespace Tests\Unit\Config;

use App\Config\ConfigurationInvalidException;
use App\Config\ConfigurationMissingException;
use App\Config\ConfigurationResolver;
use App\Config\ConfigurationScope;
use PHPUnit\Framework\TestCase;

final class ConfigurationResolverTest extends TestCase
{
    public function testFixedMarketValuesResolveFromRegistry(): void
    {
        $resolver = new ConfigurationResolver();

        self::assertSame('FR', $resolver->resolve('market.country'));
        self::assertSame('EUR', $resolver->resolve('market.currency'));
        self::assertSame('fr-FR', $resolver->resolve('market.locale'));
        self::assertSame('Europe/Paris', $resolver->resolve('market.timezone'));
        self::assertSame('INCO', $resolver->resolve('market.allergen_standard'));
    }

    public function testTenantValuesAreResolvedAndTypedFromLegacyStorageKeys(): void
    {
        $resolver = new ConfigurationResolver([
            'site_nom' => 'Maison Exemple',
            'livraison_rayon_max_km' => '42.5',
            'commandes_max_par_jour' => '18',
            'livraison_cp_gratuits' => '33000, 33100;33200',
        ]);

        self::assertSame('Maison Exemple', $resolver->resolve('brand.name'));
        self::assertSame(42.5, $resolver->resolve('delivery.radius_km'));
        self::assertSame(18, $resolver->resolve('order.capacity.max_per_day'));
        self::assertSame(['33000', '33100', '33200'], $resolver->resolve('delivery.free_postal_codes'));
    }

    public function testMissingRequiredValueFailsClosed(): void
    {
        $resolver = new ConfigurationResolver();

        $this->expectException(ConfigurationMissingException::class);
        $resolver->resolve('brand.name');
    }

    public function testInvalidPersistedValueFailsClosedInsteadOfUsingDefault(): void
    {
        $resolver = new ConfigurationResolver([
            'couleur_primaire' => 'not-a-color',
        ]);

        $this->expectException(ConfigurationInvalidException::class);
        $resolver->resolve('theme.primary_color');
    }

    public function testOptionalMissingValueCanResolveToNull(): void
    {
        $resolver = new ConfigurationResolver();

        self::assertNull($resolver->resolve('business.vat_number'));
        self::assertNull($resolver->resolve('delivery.free_postal_codes'));
    }

    public function testDeclaredDefaultIsUsedOnlyWhenAllowed(): void
    {
        $resolver = new ConfigurationResolver();

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
            ['order.number_prefix', 'tax.regime'],
            $resolver->missingRequired(ConfigurationScope::TENANT),
        );
    }

    public function testOperatorEnvironmentValuesUseTheSameTypedContract(): void
    {
        $resolver = new ConfigurationResolver([], [
            'APP_ENV' => 'production',
            'DB_HOST' => 'db.internal',
            'DB_PORT' => '3307',
        ]);

        self::assertSame('production', $resolver->resolve('operator.app_env'));
        self::assertSame('db.internal', $resolver->resolve('operator.db.host'));
        self::assertSame(3307, $resolver->resolve('operator.db.port'));
    }

    public function testInvalidOperatorEnumFailsClosed(): void
    {
        $resolver = new ConfigurationResolver([], ['APP_ENV' => 'prod-ish']);

        $this->expectException(ConfigurationInvalidException::class);
        $resolver->resolve('operator.app_env');
    }
}
