<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class CanonicalCatalogContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testMenuModelIsReadOnlyForCatalogMutations(): void
    {
        $model = $this->source('src/Models/MenuModel.php');

        foreach ([
            'INSERT INTO menu',
            'UPDATE menu SET',
            'DELETE FROM menu',
            'INSERT INTO plat',
            'UPDATE plat SET',
            'DELETE FROM plat',
            'INSERT INTO menu_plat',
            'DELETE FROM menu_plat',
            'INSERT INTO plat_allergen',
            'DELETE FROM plat_allergen',
            'INSERT INTO menu_image',
            'DELETE FROM menu_image',
        ] as $writeSql) {
            self::assertStringNotContainsString($writeSql, $model);
        }
    }

    public function testEmployeeCatalogControllerUsesSingleCanonicalWriteService(): void
    {
        $controller = $this->source('src/Controllers/Workspace/MenuAdminController.php');

        foreach ([
            'CatalogIntegrityService::createMenu',
            'CatalogIntegrityService::updateMenu',
            'CatalogIntegrityService::deactivateMenu',
            'CatalogIntegrityService::createPlat',
            'CatalogIntegrityService::updatePlat',
            'CatalogIntegrityService::deletePlat',
            'CatalogIntegrityService::detachImage',
        ] as $call) {
            self::assertStringContainsString($call, $controller);
        }

        self::assertStringNotContainsString('MenuModel::delete', $controller);
        self::assertStringNotContainsString('MenuModel::create', $controller);
        self::assertStringNotContainsString('MenuModel::update', $controller);
    }

    public function testLegacyCatalogImageWriteWrappersAreGone(): void
    {
        $service = $this->source('src/Services/MenuAdminService.php');

        self::assertStringNotContainsString('uploadMenuImages(', $service);
        self::assertStringNotContainsString('deleteMenuImageFile(', $service);
        self::assertStringNotContainsString('use App\\Models\\MenuModel;', $service);
    }

    public function testBaselineKeepsCatalogForeignKeysAndNoTenantCatalogSeed(): void
    {
        $baseline = $this->source('sql/v1/001_v1_baseline.sql');

        foreach ([
            'fk_menu_theme FOREIGN KEY (theme_id) REFERENCES theme(theme_id) ON DELETE SET NULL',
            'fk_menu_regime FOREIGN KEY (regime_id) REFERENCES regime(regime_id) ON DELETE SET NULL',
            'fk_plat_categorie FOREIGN KEY (categorie_id) REFERENCES categorie_plat(categorie_id) ON DELETE RESTRICT',
            'fk_menu_plat_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE',
            'fk_menu_plat_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE',
            'fk_plat_allergen_allergen FOREIGN KEY (allergen_id) REFERENCES allergen(allergen_id) ON DELETE RESTRICT',
        ] as $constraint) {
            self::assertStringContainsString($constraint, $baseline);
        }

        self::assertStringNotContainsString('INSERT INTO menu ', $baseline);
        self::assertStringNotContainsString('INSERT INTO theme ', $baseline);
        self::assertStringNotContainsString('INSERT INTO regime ', $baseline);
        self::assertSame(14, substr_count($baseline, "'gluten'") + 13);
        self::assertStringContainsString("(14, 'mollusques'", $baseline);
    }

    public function testCanonicalServiceOwnsTransactionalDeletionRules(): void
    {
        $service = $this->source('src/Services/CatalogIntegrityService.php');

        self::assertStringContainsString('public static function deactivateMenu', $service);
        self::assertStringContainsString('public static function deletePlat', $service);
        self::assertStringContainsString('$db->beginTransaction()', $service);
        self::assertStringContainsString('SELECT COUNT(*) FROM menu_plat WHERE plat_id = ?', $service);
        self::assertStringContainsString('UPDATE menu SET actif = 0 WHERE menu_id = ?', $service);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, 'Unable to read ' . $path);
        return $source;
    }
}
