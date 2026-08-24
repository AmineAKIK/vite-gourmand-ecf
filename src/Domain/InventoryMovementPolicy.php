<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final class InventoryMovementPolicy
{
    public static function assertType(string $type): void
    {
        if (!in_array($type, ['entree', 'sortie', 'ajustement'], true)) {
            throw new InvalidArgumentException('Type de mouvement invalide.');
        }
    }

    public static function assertQuantity(float $quantity): void
    {
        if (!is_finite($quantity) || $quantity <= 0) {
            throw new InvalidArgumentException('La quantité doit être positive.');
        }
    }

    public static function reversalType(string $type): string
    {
        self::assertType($type);

        return $type === 'sortie' ? 'entree' : 'sortie';
    }

    public static function orderConsumptionKey(int $commandeId, int $ingredientId): string
    {
        if ($commandeId <= 0 || $ingredientId <= 0) {
            throw new InvalidArgumentException('Identifiant de mouvement invalide.');
        }

        return 'order:' . $commandeId . ':consume:' . $ingredientId;
    }

    public static function reversalKey(int $mouvementId): string
    {
        if ($mouvementId <= 0) {
            throw new InvalidArgumentException('Mouvement invalide.');
        }

        return 'stock:reversal:' . $mouvementId;
    }
}
