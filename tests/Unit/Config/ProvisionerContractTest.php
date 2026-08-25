<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\Provisioner;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class ProvisionerContractTest extends TestCase
{
    public function testRequiredTablesRepresentTheCanonicalRuntimeSurface(): void
    {
        $required = Provisioner::requiredTables();

        self::assertContains('utilisateur', $required);
        self::assertContains('plat_allergen', $required);
        self::assertContains('document_facturation', $required);
        self::assertContains('order_draft', $required);
        self::assertContains('payment_refund_attempt', $required);
        self::assertContains('cron_rappel_log', $required);
        self::assertNotContains('plat_allergene', $required);
        self::assertNotContains('schema_migrations', $required);
        self::assertNotContains('geocache', $required);
    }

    public function testCompleteSchemaIsAccepted(): void
    {
        $method = new ReflectionMethod(Provisioner::class, 'assertCompleteExistingSchema');
        $method->setAccessible(true);

        $method->invoke(null, Provisioner::requiredTables());
        self::assertTrue(true);
    }

    public function testPartialSchemaFailsClosed(): void
    {
        $method = new ReflectionMethod(Provisioner::class, 'assertCompleteExistingSchema');
        $method->setAccessible(true);

        $tables = Provisioner::requiredTables();
        $tables = array_values(array_filter($tables, static fn(string $table): bool => $table !== 'paiement'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('paiement');
        $method->invoke(null, $tables);
    }

    public function testTrackingTableAloneCannotSatisfyExistingSchemaContract(): void
    {
        $method = new ReflectionMethod(Provisioner::class, 'assertCompleteExistingSchema');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $method->invoke(null, ['schema_migrations']);
    }

    public function testInstallerHasNoSecondSqlMigrationEngineOrOwnerLicenceBootstrap(): void
    {
        $installer = file_get_contents(dirname(__DIR__, 3) . '/install/bootstrap.php');
        self::assertNotFalse($installer);

        self::assertStringContainsString('Provisioner::run()', $installer);
        self::assertStringContainsString('Migrator::run()', $installer);
        self::assertStringNotContainsString("SQL_DIR . '/schema.sql'", $installer);
        self::assertStringNotContainsString("glob(MIGRATIONS", $installer);
        self::assertStringNotContainsString('license_hash', $installer);
        self::assertStringNotContainsString('tugeres_akiksystems_2025_', $installer);
    }

    public function testStartupScriptRunsProvisioningBeforeForwardMigrations(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/bin/migrate.php');
        self::assertNotFalse($script);

        $provisioner = strpos($script, 'Provisioner::run();');
        $migrator = strpos($script, 'Migrator::run();');

        self::assertIsInt($provisioner);
        self::assertIsInt($migrator);
        self::assertLessThan($migrator, $provisioner);
    }
}
