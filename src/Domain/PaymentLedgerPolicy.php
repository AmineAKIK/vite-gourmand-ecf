<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

final class PaymentLedgerPolicy
{
    public static function assertCollectionAmount(int $amountCents, int $netCollectedCents, int $orderTotalCents): void
    {
        if ($amountCents <= 0 || $netCollectedCents < 0 || $orderTotalCents < 0) {
            throw new RuntimeException('Montants de paiement invalides.');
        }
        if ($netCollectedCents + $amountCents > $orderTotalCents) {
            throw new RuntimeException('Le paiement dépasserait le montant total de la commande.');
        }
    }

    public static function assertOrderEditable(int $netCollectedCents): void
    {
        if ($netCollectedCents > 0) {
            throw new RuntimeException('Une commande déjà encaissée ne peut plus être modifiée.');
        }
    }

    public static function assertManualCancellationAllowed(int $netManualCents): void
    {
        if ($netManualCents > 0) {
            throw new RuntimeException(
                'Des paiements manuels restent encaissés. Contre-passez-les avant d’annuler la commande.',
            );
        }
    }

    public static function collectionOperationKey(int $paymentId): string
    {
        return 'payment:' . $paymentId . ':reversal';
    }

    public static function stripeRefundOperationKey(int $paymentId): string
    {
        return 'stripe-refund:payment:' . $paymentId;
    }
}
