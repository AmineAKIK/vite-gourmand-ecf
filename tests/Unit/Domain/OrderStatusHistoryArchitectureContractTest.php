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
        $providerWebhook = $this->source('src/Services/PaymentWebhookFulfillmentService.php');
        $history = $this->source('src/Services/OrderStatusHistoryService.php');

        self::assertStringContainsString('OrderStatusHistoryService::append(', $transition);
        self::assertStringContainsString('OrderStatusHistoryService::append(', $cancellation);
        self::assertStringContainsString('OrderStatusHistoryService::append(', $providerWebhook);
        self::assertStringNotContainsString('INSERT INTO commande_historique', $transition);
        self::assertStringNotContainsString('INSERT INTO commande_historique', $cancellation);
        self::assertStringNotContainsString('CommandeModel::addHistorique', $providerWebhook);
        self::assertStringContainsString('INSERT INTO commande_historique_guard', $history);
        self::assertStringContainsString('nouveau_statut_guard', $history);
    }

    public function testMigrationMakesHistoryRelationallyImmutableWithoutPrivilegedTriggers(): void
    {
        $migration = $this->source('sql/v1/migrations/004_immutable_order_status_history.sql');

        self::assertStringContainsString('CREATE TABLE commande_historique_guard', $migration);
        self::assertStringContainsString('uk_commande_historique_immutable', $migration);
        self::assertStringContainsString('fk_commande_historique_guard_event', $migration);
        self::assertStringContainsString('ancien_statut_guard BINARY(32)', $migration);
        self::assertStringContainsString('nouveau_statut_guard BINARY(32)', $migration);
        self::assertStringContainsString('commentaire_guard BINARY(32)', $migration);
        self::assertStringContainsString("UNHEX(SHA2(COALESCE(commentaire, ''), 256))", $migration);
        self::assertStringContainsString('ON UPDATE RESTRICT ON DELETE RESTRICT', $migration);
        self::assertSame(3, substr_count($migration, 'ON DELETE RESTRICT'));
        self::assertStringNotContainsString('CREATE TRIGGER', $migration);
    }

    public function testMigrationDiscoversHistoricalForeignKeysByRelationship(): void
    {
        $migration = $this->source('sql/v1/migrations/004_immutable_order_status_history.sql');

        self::assertStringContainsString('information_schema.KEY_COLUMN_USAGE', $migration);
        self::assertStringContainsString("COLUMN_NAME = 'commande_id'", $migration);
        self::assertStringContainsString("REFERENCED_TABLE_NAME = 'commande'", $migration);
        self::assertStringContainsString("COLUMN_NAME = 'modifie_par'", $migration);
        self::assertStringContainsString("REFERENCED_TABLE_NAME = 'utilisateur'", $migration);
        self::assertStringContainsString('PREPARE drop_history_order_fks_stmt', $migration);
        self::assertStringContainsString('PREPARE drop_history_actor_fks_stmt', $migration);
        self::assertStringNotContainsString('DROP FOREIGN KEY fk_commande_historique_commande', $migration);
        self::assertStringNotContainsString('DROP FOREIGN KEY fk_commande_historique_user', $migration);
    }

    public function testMigrationCanResumeAfterUntrackedDerivedGuardArtifacts(): void
    {
        $migration = $this->source('sql/v1/migrations/004_immutable_order_status_history.sql');

        self::assertStringContainsString('DROP TABLE IF EXISTS commande_historique_guard', $migration);
        self::assertStringContainsString('uk_commande_historique_immutable', $migration);
        self::assertStringContainsString('information_schema.STATISTICS', $migration);
        self::assertStringContainsString('PREPARE drop_history_guard_columns_stmt', $migration);
        self::assertStringContainsString("'nouveau_statut_guard'", $migration);
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
