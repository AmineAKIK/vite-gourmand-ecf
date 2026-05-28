<?php

namespace App\Models;

use App\Config\Database;

class StockModel
{
    public static function getMouvements(int $ingredientId, int $limit = 50): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT ms.*, u.prenom, u.nom
             FROM mouvement_stock ms
             LEFT JOIN utilisateur u ON u.utilisateur_id = ms.cree_par
             WHERE ms.ingredient_id = ?
             ORDER BY ms.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$ingredientId, $limit]);
        return $stmt->fetchAll();
    }

    public static function getTousMovements(int $limit = 200): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT ms.*, i.libelle AS ingredient, i.unite,
                    u.prenom, u.nom
             FROM mouvement_stock ms
             JOIN ingredient i ON i.ingredient_id = ms.ingredient_id
             LEFT JOIN utilisateur u ON u.utilisateur_id = ms.cree_par
             ORDER BY ms.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function addMouvement(int $ingredientId, string $type, float $quantite, ?string $motif, ?int $commandeId, ?int $creePar): int
    {
        if (!in_array($type, ['entree', 'sortie', 'ajustement'], true)) {
            throw new \InvalidArgumentException('Type de mouvement invalide.');
        }
        if ($quantite <= 0) {
            throw new \InvalidArgumentException('La quantité doit être positive.');
        }
        $db = Database::getConnection();
        $db->prepare(
            'INSERT INTO mouvement_stock (ingredient_id, type_mouvement, quantite, motif, commande_id, cree_par)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$ingredientId, $type, $quantite, $motif, $commandeId, $creePar]);
        return (int)$db->lastInsertId();
    }

    public static function deleteMouvement(int $mouvementId): void
    {
        Database::getConnection()->prepare(
            'DELETE FROM mouvement_stock WHERE mouvement_id = ?'
        )->execute([$mouvementId]);
    }

    /** Consomme les ingrédients de tous les plats d'une commande (appel optionnel à la livraison) */
    public static function consommerPourCommande(int $commandeId, ?int $creePar): void
    {
        $db = Database::getConnection();

        // Récupérer les plats de la commande via les lignes de commande → menus → plats
        $stmt = $db->prepare(
            'SELECT mp.plat_id, lc.nombre_personne,
                    rl.ingredient_id, rl.grammage
             FROM ligne_commande lc
             JOIN menu_plat mp ON mp.menu_id = lc.menu_id
             JOIN recette_ligne rl ON rl.plat_id = mp.plat_id
             WHERE lc.commande_id = ?'
        );
        $stmt->execute([$commandeId]);
        $rows = $stmt->fetchAll();

        $consommation = [];
        foreach ($rows as $row) {
            $iid  = (int)$row['ingredient_id'];
            $qty  = (float)$row['grammage'] * (int)$row['nombre_personne'];
            $consommation[$iid] = ($consommation[$iid] ?? 0) + $qty;
        }

        foreach ($consommation as $ingredientId => $quantite) {
            self::addMouvement($ingredientId, 'sortie', $quantite, "Consommation commande #{$commandeId}", $commandeId, $creePar);
        }
    }
}
