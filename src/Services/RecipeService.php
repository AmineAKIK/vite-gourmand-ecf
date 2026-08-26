<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\CatalogIntegrityPolicy;
use PDO;
use RuntimeException;
use Throwable;

final class RecipeService
{
    public static function save(int $platId, array $lines): void
    {
        $lines = CatalogIntegrityPolicy::recipeLines($lines);
        $ingredientIds = array_column($lines, 'ingredient_id');

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::lockPlat($db, $platId);
            self::assertActiveIngredientIds($db, $ingredientIds);

            $db->prepare('DELETE FROM recette_ligne WHERE plat_id = ?')->execute([$platId]);
            if ($lines !== []) {
                $insert = $db->prepare(
                    'INSERT INTO recette_ligne (plat_id, ingredient_id, grammage) VALUES (?, ?, ?)',
                );
                foreach ($lines as $line) {
                    $insert->execute([$platId, $line['ingredient_id'], $line['grammage']]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function lockPlat(PDO $db, int $platId): void
    {
        $stmt = $db->prepare('SELECT plat_id FROM plat WHERE plat_id = ? FOR UPDATE');
        $stmt->execute([$platId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Plat introuvable.');
        }
    }

    private static function assertActiveIngredientIds(PDO $db, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT ingredient_id FROM ingredient
             WHERE actif = 1 AND ingredient_id IN ({$placeholders})
             ORDER BY ingredient_id
             FOR UPDATE",
        );
        $stmt->execute($ids);
        $found = array_map('intval', array_column($stmt->fetchAll(), 'ingredient_id'));
        sort($found);
        $expected = array_values(array_unique(array_map('intval', $ids)));
        sort($expected);
        if ($found !== $expected) {
            throw new RuntimeException('Ingrédient introuvable ou désactivé.');
        }
    }
}
