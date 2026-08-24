<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\CatalogIntegrityPolicy;
use RuntimeException;
use Throwable;

final class IngredientCatalogService
{
    public static function create(array $payload): int
    {
        $data = CatalogIntegrityPolicy::ingredientPayload($payload);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO ingredient (libelle, unite, prix_unitaire, seuil_alerte, actif) VALUES (?, ?, ?, ?, 1)',
            );
            $stmt->execute([$data['libelle'], $data['unite'], $data['prix_unitaire'], $data['seuil_alerte']]);
            $id = (int) $db->lastInsertId();
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function update(int $ingredientId, array $payload): void
    {
        $data = CatalogIntegrityPolicy::ingredientPayload($payload);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::lockActive($ingredientId);
            $db->prepare(
                'UPDATE ingredient SET libelle = ?, unite = ?, prix_unitaire = ?, seuil_alerte = ? WHERE ingredient_id = ?',
            )->execute([$data['libelle'], $data['unite'], $data['prix_unitaire'], $data['seuil_alerte'], $ingredientId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deactivate(int $ingredientId): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::lockActive($ingredientId);
            $db->prepare('UPDATE ingredient SET actif = 0 WHERE ingredient_id = ?')->execute([$ingredientId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function lockActive(int $ingredientId): void
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT ingredient_id FROM ingredient WHERE ingredient_id = ? AND actif = 1 FOR UPDATE',
        );
        $stmt->execute([$ingredientId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Ingrédient introuvable ou déjà désactivé.');
        }
    }
}
