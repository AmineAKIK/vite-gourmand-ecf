<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class ConfigurationAdminContractTest extends TestCase
{
    public function testAdminControllerUsesRegistryWriterInsteadOfParallelValidationTable(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/Admin/ParametresController.php',
        );
        self::assertIsString($controller);

        self::assertStringContainsString('ConfigurationRegistry::siteConfigDefinitions()', $controller);
        self::assertStringContainsString('ConfigurationWriter::write(', $controller);
        self::assertStringContainsString('ConfigurationWriter::writeStorageKey(', $controller);
        self::assertStringNotContainsString('$allFields', $controller);
        self::assertStringNotContainsString("case 'decimal'", $controller);
        self::assertStringNotContainsString('cron_secret_token', $controller);
    }

    public function testCronAuthenticationUsesOperatorConfigurationNotTenantDatabase(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controllers/CronController.php');
        self::assertIsString($controller);

        self::assertStringContainsString("Configuration::get('operator.cron.token')", $controller);
        self::assertStringContainsString("HTTP_X_CRON_TOKEN", $controller);
        self::assertStringNotContainsString('SiteConfig::get', $controller);
        self::assertStringNotContainsString('cron_secret_token', $controller);
    }
}
