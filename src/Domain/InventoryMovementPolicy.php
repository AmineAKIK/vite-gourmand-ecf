<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final class InventoryMovementPolicy
{
    public static function assertType(string $type): void
    {
        if (!in_array($type, ['entree', 'sortie'], true)) {
            throw new InvalidArgumentException('Type de mouvement invalide. Les nouveaux mouvements doivent être des entrées ou des sorties.');
        }
    }

    public static function normalizeQuantity(mixed $quantity): string
    {
        return InventoryQuantity::normalizePositive($quantity);
    }

    public static function assertQuantity(mixed $quantity): void
    {
        self::normalizeQuantity($quantity);
    }

    public static function reversalType(string $type): string
    {
        return match ($type) {
            'entree' => 'sortie',
            'sortie' => 'entree',
            // Historical V1 rows used `ajustement` as a positive delta. A reversal
            // must therefore subtract the same quantity without allowing new
            // ambiguous adjustment movements to be created.
            'ajustement' => 'sortie',
            default => throw new InvalidArgumentException('Type de mouvement historique invalide.'),
        };
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
