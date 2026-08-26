<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class OrderAvailabilityArchitectureContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testPublicAvailabilityEndpointDelegatesToCanonicalService(): void
    {
        $controller = $this->source('src/Controllers/CommandeController.php');

        self::assertStringContainsString('OrderAvailabilityService::checkDate(Database::getConnection(), $date)', $controller);
        self::assertStringContainsString("'available' => $availability['available']", $controller);
        self::assertStringContainsString("'month_count' => $availability['month_count']", $controller);
    }

    public function testServerCheckoutValidationUsesSameScheduleEngine(): void
    {
        $service = $this->source('src/Services/CommandeService.php');

        self::assertStringContainsString('OrderAvailabilityService::assertServiceAt($datePrestation', $service);
        self::assertStringNotContainsString('new BusinessPolicy', $service);
    }

    public function testBlackoutDatesAreTenantConfigurationNotHardcodedCalendarData(): void
    {
        $registry = $this->source('src/Config/ConfigurationRegistry.php');
        $view = $this->source('src/Views/pages/admin/parametres.php');
        $baseline = $this->source('sql/v1/001_v1_baseline.sql');

        self::assertStringContainsString("'order.blackout_dates'", $registry);
        self::assertStringContainsString('name="commande_dates_fermees"', $view);
        self::assertStringNotContainsString('INSERT INTO horaire', $baseline);
    }

    public function testCheckoutPresentationConsumesAvailabilityMessage(): void
    {
        $view = $this->source('src/Views/pages/panier/index.php');

        self::assertStringContainsString('data.available === false', $view);
        self::assertStringContainsString('data.message', $view);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, 'Unable to read ' . $path);
        return $source;
    }
}
