<?php

namespace App\Models;

use App\Config\Database;

class IngredientModel
{
    public static function getAll(bool $actifsOnly = false): array
    {
        $db   = Database::getConnection();
        $sql  = 'SELECT i.*, COALESCE(s.stock_courant, 0) AS stock_courant
                 FROM ingredient i
                 LEFT JOIN (
                     SELECT ingredient_id,
                            SUM(CASE WHEN type_mouvement = \'entree\'     THEN quantite
                                     WHEN type_mouvement = \'sortie\'     THEN -quantite
                                     WHEN type_mouvement = \'ajustement\' THEN quantite
                                     ELSE 0 END) AS stock_courant
                     FROM mouvement_stock
                     GROUP BY ingredient_id
                 ) s ON s.ingredient_id = i.ingredient_id';
        if ($actifsOnly) {
            $sql .= ' WHERE i.actif = 1';
        }
        $sql .= ' ORDER BY i.libelle ASC';
        return $db->query($sql)->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM ingredient WHERE ingredient_id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $db->prepare(
            'INSERT INTO ingredient (libelle, unite, prix_unitaire, seuil_alerte) VALUES (?, ?, ?, ?)'
        )->execute([
            $data['libelle'],
            $data['unite']        ?? 'kg',
            (float)($data['prix_unitaire'] ?? 0),
            isset($data['seuil_alerte']) && $data['seuil_alerte'] !== '' ? (float)$data['seuil_alerte'] : null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::getConnection()->prepare(
            'UPDATE ingredient SET libelle=?, unite=?, prix_unitaire=?, seuil_alerte=? WHERE ingredient_id=?'
        )->execute([
            $data['libelle'],
            $data['unite']        ?? 'kg',
            (float)($data['prix_unitaire'] ?? 0),
            isset($data['seuil_alerte']) && $data['seuil_alerte'] !== '' ? (float)$data['seuil_alerte'] : null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::getConnection()->prepare(
            'DELETE FROM ingredient WHERE ingredient_id = ?'
        )->execute([$id]);
    }

    /** Stock courant calculé depuis les mouvements */
    public static function stockCourant(int $id): float
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COALESCE(SUM(
                CASE WHEN type_mouvement = 'entree'     THEN quantite
                     WHEN type_mouvement = 'sortie'     THEN -quantite
                     WHEN type_mouvement = 'ajustement' THEN quantite
                     ELSE 0 END
            ), 0) FROM mouvement_stock WHERE ingredient_id = ?"
        );
        $stmt->execute([$id]);
        return (float)$stmt->fetchColumn();
    }

    /** Ingrédients dont le stock courant est sous le seuil d'alerte */
    public static function getSousSeuilAlerte(): array
    {
        $db = Database::getConnection();
        return $db->query(
            "SELECT i.*, COALESCE(s.stock_courant, 0) AS stock_courant
             FROM ingredient i
             LEFT JOIN (
                 SELECT ingredient_id,
                        SUM(CASE WHEN type_mouvement = 'entree'     THEN quantite
                                 WHEN type_mouvement = 'sortie'     THEN -quantite
                                 WHEN type_mouvement = 'ajustement' THEN quantite
                                 ELSE 0 END) AS stock_courant
                 FROM mouvement_stock GROUP BY ingredient_id
             ) s ON s.ingredient_id = i.ingredient_id
             WHERE i.actif = 1
               AND i.seuil_alerte IS NOT NULL
               AND COALESCE(s.stock_courant, 0) < i.seuil_alerte
             ORDER BY i.libelle"
        )->fetchAll();
    }
}
