<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\Money;
use App\Domain\OrderStatus;
use App\Domain\PaymentLedgerPolicy;
use PDO;
use RuntimeException;
use Throwable;

final class OrderCancellationService
{
    /**
     * @return array{commande_id:int, ancien_statut:string, nouveau_statut:string, changed:bool}
     */
    public static function cancel(
        int $commandeId,
        string $motif,
        string $modeContact,
        ?int $modifiePar,
    ): array {
        $motif = trim($motif);
        $modeContact = trim($modeContact);
        if ($commandeId <= 0 || $motif === '' || $modeContact === '') {
            throw new RuntimeException('Le motif et le mode de contact sont obligatoires pour une annulation.');
        }

        $refunds = self::prepareRefunds($commandeId);
        foreach ($refunds as $refund) {
            if ((string) $refund['status'] !== 'succeeded') {
                self::performStripeRefund($refund);
            }
        }

        return self::finalizeCancellation($commandeId, $motif, $modeContact, $modifiePar);
    }

    private static function prepareRefunds(int $commandeId): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $order = self::lockOrder($db, $commandeId);
            $status = (string) $order['statut'];
            if ($status === OrderStatus::cancelled()) {
                $db->commit();
                return [];
            }
            if (!OrderStatus::canTransition($status, OrderStatus::cancelled())) {
                throw new RuntimeException(sprintf(
                    'Transition interdite : %s → %s.',
                    OrderStatus::label($status),
                    OrderStatus::label(OrderStatus::cancelled()),
                ));
            }

            PaymentLedgerPolicy::assertManualCancellationAllowed(
                PaymentLedgerService::netManualCollectedCents($db, $commandeId),
            );

            $payments = PaymentLedgerService::stripeCollectionsForOrder($db, $commandeId);
            $attempts = [];
            foreach ($payments as $payment) {
                $paymentId = (int) $payment['paiement_id'];
                $reference = trim((string) ($payment['reference'] ?? ''));
                if ($reference === '') {
                    throw new RuntimeException('Référence Stripe absente pour un paiement à rembourser.');
                }
                $operationKey = PaymentLedgerPolicy::stripeRefundOperationKey($paymentId);
                $stmt = $db->prepare(
                    "INSERT INTO payment_refund_attempt
                        (paiement_id, commande_id, operation_key, provider, provider_payment_reference, amount_cents, status)
                     VALUES (?, ?, ?, 'stripe', ?, ?, 'pending')
                     ON DUPLICATE KEY UPDATE refund_attempt_id = LAST_INSERT_ID(refund_attempt_id)",
                );
                $stmt->execute([
                    $paymentId,
                    $commandeId,
                    $operationKey,
                    $reference,
                    Money::fromDecimal((string) $payment['montant']),
                ]);

                $find = $db->prepare('SELECT * FROM payment_refund_attempt WHERE paiement_id = ? FOR UPDATE');
                $find->execute([$paymentId]);
                $attempt = $find->fetch();
                if (!$attempt) {
                    throw new RuntimeException('Tentative de remboursement introuvable.');
                }
                $attempts[] = $attempt;
            }

            $db->commit();
            return $attempts;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function performStripeRefund(array $attempt): void
    {
        if (!defined('STRIPE_SECRET_KEY') || !STRIPE_SECRET_KEY || str_starts_with(STRIPE_SECRET_KEY, 'sk_test_REMPLACER')) {
            throw new RuntimeException('Stripe n’est pas configuré : remboursement impossible.');
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $paymentReference = (string) $attempt['provider_payment_reference'];

        try {
            if (str_starts_with($paymentReference, 'cs_')) {
                $session = \Stripe\Checkout\Session::retrieve($paymentReference);
                $paymentReference = (string) ($session->payment_intent ?? '');
            }
            if (!str_starts_with($paymentReference, 'pi_')) {
                throw new RuntimeException('Référence de PaymentIntent Stripe introuvable.');
            }

            $refund = \Stripe\Refund::create([
                'payment_intent' => $paymentReference,
                'amount' => (int) $attempt['amount_cents'],
                'metadata' => [
                    'commande_id' => (string) $attempt['commande_id'],
                    'paiement_id' => (string) $attempt['paiement_id'],
                ],
            ], [
                'idempotency_key' => (string) $attempt['operation_key'],
            ]);

            $db = Database::getConnection();
            $stmt = $db->prepare(
                "UPDATE payment_refund_attempt
                 SET status = 'succeeded', provider_refund_id = ?, last_error = NULL
                 WHERE refund_attempt_id = ?",
            );
            $stmt->execute([(string) $refund->id, (int) $attempt['refund_attempt_id']]);
        } catch (Throwable $e) {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "UPDATE payment_refund_attempt
                 SET status = 'failed', last_error = ?
                 WHERE refund_attempt_id = ?",
            );
            $stmt->execute([substr($e->getMessage(), 0, 500), (int) $attempt['refund_attempt_id']]);
            throw new RuntimeException('Le remboursement Stripe a échoué. La commande n’a pas été annulée.', 0, $e);
        }
    }

    /**
     * @return array{commande_id:int, ancien_statut:string, nouveau_statut:string, changed:bool}
     */
    private static function finalizeCancellation(
        int $commandeId,
        string $motif,
        string $modeContact,
        ?int $modifiePar,
    ): array {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $order = self::lockOrder($db, $commandeId);
            $oldStatus = (string) $order['statut'];
            if ($oldStatus === OrderStatus::cancelled()) {
                $db->commit();
                return [
                    'commande_id' => $commandeId,
                    'ancien_statut' => $oldStatus,
                    'nouveau_statut' => $oldStatus,
                    'changed' => false,
                ];
            }
            if (!OrderStatus::canTransition($oldStatus, OrderStatus::cancelled())) {
                throw new RuntimeException(sprintf(
                    'Transition interdite : %s → %s.',
                    OrderStatus::label($oldStatus),
                    OrderStatus::label(OrderStatus::cancelled()),
                ));
            }

            PaymentLedgerPolicy::assertManualCancellationAllowed(
                PaymentLedgerService::netManualCollectedCents($db, $commandeId),
            );

            $payments = PaymentLedgerService::stripeCollectionsForOrder($db, $commandeId);
            foreach ($payments as $payment) {
                $attempt = $db->prepare(
                    "SELECT provider_refund_id FROM payment_refund_attempt
                     WHERE paiement_id = ? AND status = 'succeeded' FOR UPDATE",
                );
                $attempt->execute([(int) $payment['paiement_id']]);
                $refundId = $attempt->fetchColumn();
                if ($refundId === false || (string) $refundId === '') {
                    throw new RuntimeException('Remboursement Stripe non confirmé : annulation bloquée.');
                }
                PaymentLedgerService::appendStripeRefund($db, $payment, (string) $refundId);
            }

            if (PaymentLedgerService::netCollectedCents($db, $commandeId) !== 0) {
                throw new RuntimeException('Un solde encaissé subsiste : annulation bloquée.');
            }

            InventoryLedgerService::restoreOrderConsumption($db, $commandeId, $modifiePar);
            self::restoreMenuStockOnce($db, $commandeId);

            $stmt = $db->prepare(
                'UPDATE commande
                 SET statut = ?, motif_annulation = ?, mode_contact_annulation = ?
                 WHERE commande_id = ?',
            );
            $stmt->execute([OrderStatus::cancelled(), $motif, $modeContact, $commandeId]);

            $history = $db->prepare(
                'INSERT INTO commande_historique
                    (commande_id, ancien_statut, nouveau_statut, commentaire, modifie_par)
                 VALUES (?, ?, ?, ?, ?)',
            );
            $history->execute([
                $commandeId,
                $oldStatus,
                OrderStatus::cancelled(),
                sprintf('Annulation (%s) : %s', $modeContact, $motif),
                $modifiePar,
            ]);

            $db->commit();
            return [
                'commande_id' => $commandeId,
                'ancien_statut' => $oldStatus,
                'nouveau_statut' => OrderStatus::cancelled(),
                'changed' => true,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function restoreMenuStockOnce(PDO $db, int $commandeId): void
    {
        $insert = $db->prepare(
            'INSERT IGNORE INTO order_cancellation_effect (commande_id) VALUES (?)',
        );
        $insert->execute([$commandeId]);
        if ($insert->rowCount() !== 1) {
            return;
        }

        $restore = $db->prepare(
            'UPDATE menu m
             JOIN (
                 SELECT menu_id, COUNT(*) AS qty
                 FROM commande_ligne
                 WHERE commande_id = ?
                 GROUP BY menu_id
             ) x ON x.menu_id = m.menu_id
             SET m.quantite_restante = m.quantite_restante + x.qty
             WHERE m.quantite_restante IS NOT NULL',
        );
        $restore->execute([$commandeId]);

        $db->prepare(
            'UPDATE order_cancellation_effect SET menu_stock_restored_at = NOW() WHERE commande_id = ?',
        )->execute([$commandeId]);
    }

    private static function lockOrder(PDO $db, int $commandeId): array
    {
        $stmt = $db->prepare('SELECT commande_id, statut FROM commande WHERE commande_id = ? FOR UPDATE');
        $stmt->execute([$commandeId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Commande introuvable.');
        }
        return $order;
    }
}
