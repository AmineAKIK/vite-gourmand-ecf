<?php

namespace App\Models;

use App\Config\Database;
use App\Services\InventoryLedgerService;
use Throwable;

class StockModel
{
    public static function getMouvements(int $ingredientId, int $limit = 50): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT ms.*, u.prenom, u.nom
             FROM mouvement_stock ms
             LEFT JOIN utilisateur u ON u.utilisateur_id = ms.cree_par
             WHERE ms.ingredient_id = ?
             ORDER BY ms.created_at DESC, ms.mouvement_id DESC
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
             ORDER BY ms.created_at DESC, ms.mouvement_id DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function addMouvement(int $ingredientId, string $type, float $quantite, ?string $motif, ?int $commandeId, ?int $creePar): int
    {
        if ($commandeId !== null) {
            throw new \InvalidArgumentException('Les mouvements de commande doivent passer par le ledger automatique.');
        }

        $db = Database::getConnection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $movementId = InventoryLedgerService::appendManualMovement(
                $db,
                $ingredientId,
                $type,
                $quantite,
                $motif,
                $creePar,
            );
            if ($ownsTransaction) {
                $db->commit();
            }

            return $movementId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Compatibilité de route historique : on ne supprime plus le mouvement,
     * on ajoute une contre-passation append-only.
     */
    public static function deleteMouvement(int $mouvementId, ?int $creePar = null): void
    {
        $db = Database::getConnection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            InventoryLedgerService::reverseManualMovement($db, $mouvementId, $creePar);
            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Consomme les ingrédients d'une commande de manière idempotente. */
    public static function consommerPourCommande(int $commandeId, ?int $creePar): void
    {
        $db = Database::getConnection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            InventoryLedgerService::consumeOrder($db, $commandeId, $creePar);
            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
