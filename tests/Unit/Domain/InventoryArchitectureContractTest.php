<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class InventoryArchitectureContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testOfflineOrderConsumesIngredientsBeforeItsTransactionCommits(): void
    {
        $model = $this->source('src/Models/CommandeModel.php');
        $consume = strpos($model, 'InventoryLedgerService::consumeOrder($db, $commandeId');
        $commit = strpos($model, '$db->commit();');

        self::assertNotFalse($consume);
        self::assertNotFalse($commit);
        self::assertLessThan($commit, $consume);
    }

    public function testCheckoutControllerHasNoPostCommitFailOpenInventoryPath(): void
    {
        $controller = $this->source('src/Controllers/CommandeController.php');

        self::assertStringNotContainsString('consommerPourCommande', $controller);
        self::assertStringNotContainsString('consommation de stock reste non bloquante', $controller);
    }

    public function testStripeConsumesInventoryInTheWebhookTransaction(): void
    {
        $service = $this->source('src/Services/StripeWebhookFulfillmentService.php');

        self::assertStringContainsString('InventoryLedgerService::consumeOrder($db, $commandeId, null)', $service);
        self::assertStringNotContainsString('StockModel::consommerPourCommande', $service);
    }

    public function testRecipeAndIngredientModelsAreReadOnly(): void
    {
        $recipe = $this->source('src/Models/RecetteModel.php');
        $ingredient = $this->source('src/Models/IngredientModel.php');

        self::assertStringNotContainsString('INSERT INTO recette_ligne', $recipe);
        self::assertStringNotContainsString('DELETE FROM recette_ligne', $recipe);
        self::assertStringNotContainsString('INSERT INTO ingredient', $ingredient);
        self::assertStringNotContainsString('UPDATE ingredient SET', $ingredient);
        self::assertStringNotContainsString('DELETE FROM ingredient', $ingredient);
    }

    public function testInventoryLedgerSerializesPerIngredientAndRejectsNegativeStock(): void
    {
        $ledger = $this->source('src/Services/InventoryLedgerService.php');

        self::assertStringContainsString('SELECT actif FROM ingredient WHERE ingredient_id = ? FOR UPDATE', $ledger);
        self::assertStringContainsString("if ($type === 'sortie')", $ledger);
        self::assertStringContainsString('Stock insuffisant : une sortie ne peut pas rendre le stock négatif.', $ledger);
        self::assertStringContainsString('movementByOperationKey($db, $operationKey)', $ledger);
    }

    public function testFreshDatabaseGateExecutesRealInventoryProof(): void
    {
        $workflow = $this->source('.github/workflows/quality.yml');

        self::assertStringContainsString('Verify inventory invariants against MySQL', $workflow);
        self::assertStringContainsString('php bin/verify-inventory-invariants.php', $workflow);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, 'Unable to read ' . $path);

        return $source;
    }
}
