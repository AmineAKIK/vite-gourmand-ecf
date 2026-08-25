<?php

namespace Tests\Unit\Config;

use App\Config\ConfigurationInvalidException;
use App\Config\ConfigurationWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigurationWriterTest extends TestCase
{
    public function testPrepareMapsCanonicalKeyToNormalizedStorageValue(): void
    {
        self::assertSame(
            ['storage_key' => 'livraison_rayon_max_km', 'value' => '42'],
            ConfigurationWriter::prepare('delivery.radius_km', '42'),
        );

        self::assertSame(
            ['storage_key' => 'banque_iban', 'value' => 'FR7612345678901234567890123'],
            ConfigurationWriter::prepare('banking.iban', 'FR76 1234 5678 9012 3456 7890 123'),
        );
    }

    public function testOptionalBlankTenantValueIsPersistedAsEmptyString(): void
    {
        self::assertSame(
            ['storage_key' => 'site_slogan', 'value' => ''],
            ConfigurationWriter::prepare('brand.slogan', '   '),
        );
    }

    public function testRequiredBlankTenantValueFailsClosed(): void
    {
        $this->expectException(ConfigurationInvalidException::class);
        ConfigurationWriter::prepare('brand.name', '');
    }

    public function testInvalidCommercialValueFailsClosed(): void
    {
        $this->expectException(ConfigurationInvalidException::class);
        ConfigurationWriter::prepare('discount.rate_percent', '101');
    }

    public function testOperatorSecretCannotBeWrittenToTenantDatabase(): void
    {
        $this->expectException(RuntimeException::class);
        ConfigurationWriter::prepare('operator.cron.token', 'secret');
    }

    public function testWrongActorRoleCannotWriteTenantConfiguration(): void
    {
        $this->expectException(RuntimeException::class);
        ConfigurationWriter::prepare('brand.name', 'Maison Exemple', 'employe');
    }
}
