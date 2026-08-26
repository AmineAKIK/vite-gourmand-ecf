<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\InventoryMovementPolicy;
use App\Domain\InventoryQuantity;
use PDO;
use RuntimeException;

final class InventoryLedgerService
{
    public static function snapshotOrderRequirements(PDO $db, int $commandeId): void
    {
        self::requireTransaction($db);
        if ($commandeId <= 0) {
            throw new RuntimeException('Commande invalide pour le snapshot de stock.');
        }

        $existing = $db->prepare('SELECT COUNT(*) FROM commande_ingredient_snapshot WHERE commande_id = ?');
        $existing->execute([$commandeId]);
        if ((int) $existing->fetchColumn() > 0) {
            return;
        }

        $stmt = $db->prepare(
            'SELECT rl.ingredient_id,
                    SUM(rl.grammage * cl.nombre_personne) AS quantite
             FROM commande_ligne cl
             JOIN menu_plat mp ON mp.menu_id = cl.menu_id
             JOIN recette_ligne rl ON rl.plat_id = mp.plat_id
             WHERE cl.commande_id = ?
             GROUP BY rl.ingredient_id
             HAVING SUM(rl.grammage * cl.nombre_personne) > 0
             ORDER BY rl.ingredient_id ASC',
        );
        $stmt->execute([$commandeId]);
        $rows = $stmt->fetchAll();

        $insert = $db->prepare(
            'INSERT INTO commande_ingredient_snapshot (commande_id, ingredient_id, quantite)
             VALUES (?, ?, ?)',
        );
        foreach ($rows as $row) {
            $quantity = InventoryQuantity::normalizePositive((string) $row['quantite']);
            $insert->execute([$commandeId, (int) $row['ingredient_id'], $quantity]);
        }
    }

    public static function consumeOrder(PDO $db, int $commandeId, ?int $creePar): void
    {
        self::requireTransaction($db);

        $lock = $db->prepare('SELECT commande_id FROM commande WHERE commande_id = ? FOR UPDATE');
        $lock->execute([$commandeId]);
        if ($lock->fetchColumn() === false) {
            throw new RuntimeException('Commande introuvable pour la consommation de stock.');
        }

        self::snapshotOrderRequirements($db, $commandeId);

        $stmt = $db->prepare(
            'SELECT ingredient_id, quantite
             FROM commande_ingredient_snapshot
             WHERE commande_id = ?
             ORDER BY ingredient_id ASC',
        );
        $stmt->execute([$commandeId]);

        foreach ($stmt->fetchAll() as $row) {
            $ingredientId = (int) $row['ingredient_id'];
            $quantity = InventoryQuantity::normalizePositive((string) $row['quantite']);
            self::appendMovement(
                $db,
                $ingredientId,
                'sortie',
                $quantity,
                'Consommation commande #' . $commandeId,
                $commandeId,
                $creePar,
                InventoryMovementPolicy::orderConsumptionKey($commandeId, $ingredientId),
                null,
                false,
            );
        }
    }

    public static function restoreOrderConsumption(PDO $db, int $commandeId, ?int $creePar): void
    {
        self::requireTransaction($db);

        $lock = $db->prepare('SELECT commande_id FROM commande WHERE commande_id = ? FOR UPDATE');
        $lock->execute([$commandeId]);
        if ($lock->fetchColumn() === false) {
            throw new RuntimeException('Commande introuvable pour la restitution de stock.');
        }

        $stmt = $db->prepare(
            "SELECT m.*
             FROM mouvement_stock m
             LEFT JOIN mouvement_stock r ON r.reversal_of_mouvement_id = m.mouvement_id
             WHERE m.commande_id = ?
               AND m.type_mouvement = 'sortie'
               AND m.operation_key LIKE ?
               AND r.mouvement_id IS NULL
             ORDER BY m.mouvement_id ASC
             FOR UPDATE",
        );
        $stmt->execute([$commandeId, 'order:' . $commandeId . ':consume:%']);

        foreach ($stmt->fetchAll() as $movement) {
            $movementId = (int) $movement['mouvement_id'];
            self::appendMovement(
                $db,
                (int) $movement['ingredient_id'],
                'entree',
                InventoryQuantity::normalizePositive((string) $movement['quantite']),
                'Restitution annulation commande #' . $commandeId,
                $commandeId,
                $creePar,
                InventoryMovementPolicy::reversalKey($movementId),
                $movementId,
                false,
            );
        }
    }

    public static function appendManualMovement(
        PDO $db,
        int $ingredientId,
        string $type,
        mixed $quantity,
        ?string $motif,
        ?int $creePar,
    ): int {
        InventoryMovementPolicy::assertType($type);

        return self::appendMovement(
            $db,
            $ingredientId,
            $type,
            InventoryMovementPolicy::normalizeQuantity($quantity),
            $motif,
            null,
            $creePar,
            null,
            null,
            true,
        );
    }

    public static function reverseManualMovement(PDO $db, int $mouvementId, ?int $creePar): int
    {
        self::requireTransaction($db);
        if ($mouvementId <= 0) {
            throw new RuntimeException('Mouvement invalide.');
        }

        $stmt = $db->prepare('SELECT * FROM mouvement_stock WHERE mouvement_id = ? FOR UPDATE');
        $stmt->execute([$mouvementId]);
        $movement = $stmt->fetch();
        if (!$movement) {
            throw new RuntimeException('Mouvement de stock introuvable.');
        }
        if (!empty($movement['commande_id'])) {
            throw new RuntimeException('Un mouvement lié à une commande ne peut pas être contre-passé manuellement.');
        }

        return self::appendMovement(
            $db,
            (int) $movement['ingredient_id'],
            InventoryMovementPolicy::reversalType((string) $movement['type_mouvement']),
            InventoryQuantity::normalizePositive((string) $movement['quantite']),
            'Contre-passation du mouvement #' . $mouvementId,
            null,
            $creePar,
            InventoryMovementPolicy::reversalKey($mouvementId),
            $mouvementId,
            false,
        );
    }

    private static function appendMovement(
        PDO $db,
        int $ingredientId,
        string $type,
        string $quantity,
        ?string $motif,
        ?int $commandeId,
        ?int $creePar,
        ?string $operationKey,
        ?int $reversalOf,
        bool $requireActiveIngredient,
    ): int {
        self::requireTransaction($db);
        InventoryMovementPolicy::assertType($type);
        $quantity = InventoryMovementPolicy::normalizeQuantity($quantity);
        if ($ingredientId <= 0) {
            throw new RuntimeException('Ingrédient invalide.');
        }

        self::lockIngredient($db, $ingredientId, $requireActiveIngredient);

        if ($operationKey !== null) {
            $existing = self::movementByOperationKey($db, $operationKey);
            if ($existing !== null) {
                self::assertSameOperation($existing, $ingredientId, $type, $quantity, $commandeId, $reversalOf);
                return (int) $existing['mouvement_id'];
            }
        }

        if ($type === 'sortie') {
            $availableMilliunits = self::currentStockMilliunits($db, $ingredientId);
            $requestedMilliunits = InventoryQuantity::milliunits($quantity);
            if ($availableMilliunits < $requestedMilliunits) {
                throw new RuntimeException('Stock insuffisant : une sortie ne peut pas rendre le stock négatif.');
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO mouvement_stock
                (ingredient_id, type_mouvement, quantite, motif, commande_id, cree_par, operation_key, reversal_of_mouvement_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $ingredientId,
            $type,
            $quantity,
            $motif,
            $commandeId,
            $creePar,
            $operationKey,
            $reversalOf,
        ]);

        return (int) $db->lastInsertId();
    }

    private static function lockIngredient(PDO $db, int $ingredientId, bool $requireActive): void
    {
        $stmt = $db->prepare('SELECT actif FROM ingredient WHERE ingredient_id = ? FOR UPDATE');
        $stmt->execute([$ingredientId]);
        $active = $stmt->fetchColumn();
        if ($active === false) {
            throw new RuntimeException('Ingrédient introuvable.');
        }
        if ($requireActive && (int) $active !== 1) {
            throw new RuntimeException('Ingrédient désactivé : mouvement manuel interdit.');
        }
    }

    private static function currentStockMilliunits(PDO $db, int $ingredientId): int
    {
        $stmt = $db->prepare(
            "SELECT COALESCE(ROUND(SUM(
                CASE
                    WHEN type_mouvement = 'entree' THEN quantite
                    WHEN type_mouvement = 'sortie' THEN -quantite
                    WHEN type_mouvement = 'ajustement' THEN quantite
                    ELSE 0
                END
            ) * 1000), 0)
             FROM mouvement_stock
             WHERE ingredient_id = ?",
        );
        $stmt->execute([$ingredientId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    private static function movementByOperationKey(PDO $db, string $operationKey): ?array
    {
        $stmt = $db->prepare(
            'SELECT mouvement_id, ingredient_id, type_mouvement, quantite, commande_id, reversal_of_mouvement_id
             FROM mouvement_stock
             WHERE operation_key = ?',
        );
        $stmt->execute([$operationKey]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @param array<string,mixed> $existing */
    private static function assertSameOperation(
        array $existing,
        int $ingredientId,
        string $type,
        string $quantity,
        ?int $commandeId,
        ?int $reversalOf,
    ): void {
        if ((int) $existing['ingredient_id'] !== $ingredientId
            || (string) $existing['type_mouvement'] !== $type
            || InventoryQuantity::normalizePositive((string) $existing['quantite']) !== $quantity
            || (int) ($existing['commande_id'] ?? 0) !== (int) ($commandeId ?? 0)
            || (int) ($existing['reversal_of_mouvement_id'] ?? 0) !== (int) ($reversalOf ?? 0)
        ) {
            throw new RuntimeException('Collision de clé idempotente du ledger de stock.');
        }
    }

    private static function requireTransaction(PDO $db): void
    {
        if (!$db->inTransaction()) {
            throw new RuntimeException('Le ledger de stock doit être modifié dans une transaction.');
        }
    }
}
