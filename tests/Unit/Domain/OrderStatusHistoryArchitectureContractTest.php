<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class OrderStatusHistoryArchitectureContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testCommandeModelHasNoParallelStatusMutationApi(): void
    {
        $model = $this->source('src/Models/CommandeModel.php');

        self::assertStringNotContainsString('function updateStatut(', $model);
        self::assertStringNotContainsString('function cancel(', $model);
        self::assertStringNotContainsString('function addHistorique(', $model);
        self::assertStringNotContainsString('INSERT INTO commande_historique', $model);
        self::assertStringContainsString('OrderStatusHistoryService::append(', $model);
    }

    public function testAllRuntimeStatusWritersUseCanonicalHistoryBoundary(): void
    {
        $transition = $this->source('src/Services/OrderTransitionService.php');
        $cancellation = $this->source('src/Services/OrderCancellationService.php');
        $stripe = $this->source('src/Services/StripeWebhookFulfillmentService.php');
        $history = $this->source('src/Services/OrderStatusHistoryService.php');

        self::assertStringContainsString('OrderStatusHistoryService::append(', $transition);
        self::assertStringContainsString('OrderStatusHistoryService::append(', $cancellation);
        self::assertStringContainsString('OrderStatusHistoryService::append(', $stripe);
        self::assertStringNotContainsString('INSERT INTO commande_historique', $transition);
        self::assertStringNotContainsString('INSERT INTO commande_historique', $cancellation);
        self::assertStringNotContainsString('CommandeModel::addHistorique', $stripe);
        self::assertStringContainsString('INSERT INTO commande_historique_guard', $history);
    }

    public function testMigrationMakesHistoryRelationallyImmutableWithoutPrivilegedTriggers(): void
    {
        $migration = $this->source('sql/v1/migrations/004_immutable_order_status_history.sql');

        self::assertStringContainsString('CREATE TABLE commande_historique_guard', $migration);
        self::assertStringContainsString('uk_commande_historique_immutable', $migration);
        self::assertStringContainsString('fk_commande_historique_guard_event', $migration);
        self::assertStringContainsString('commentaire_guard CHAR(64)', $migration);
        self::assertStringContainsString("SHA2(COALESCE(commentaire, ''), 256)", $migration);
        self::assertStringContainsString('ON UPDATE RESTRICT ON DELETE RESTRICT', $migration);
        self::assertSame(3, substr_count($migration, 'ON DELETE RESTRICT'));
        self::assertStringNotContainsString('CREATE TRIGGER', $migration);
    }

    public function testEmployeeDeletionPreservesActorLinkByAnonymizingWhenAudited(): void
    {
        $model = $this->source('src/Models/UserModel.php');

        self::assertStringNotContainsString('UPDATE commande_historique SET modifie_par = NULL', $model);
        self::assertStringContainsString('SELECT 1 FROM commande_historique WHERE modifie_par = ? LIMIT 1', $model);
        self::assertStringContainsString('employe-supprime-', $model);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, 'Unable to read ' . $path);

        return $source;
    }
}
