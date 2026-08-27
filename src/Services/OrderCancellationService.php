<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Configuration;
use App\Config\Database;
use App\Domain\BusinessPolicy;
use App\Domain\OrderStatus;
use App\Domain\PaymentLedgerPolicy;
use App\Payments\PaymentGatewayFactory;
use DateTimeImmutable;
use DateTimeZone;
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

        $refunds = self::prepareLedgerRefunds($commandeId);
        foreach ($refunds as $refund) {
            if ((string) $refund['status'] !== 'succeeded') {
                self::performLedgerProviderRefund($refund);
            }
        }

        self::refundAdditionalProviderAttempts($commandeId);

        return self::finalizeCancellation($commandeId, $motif, $modeContact, $modifiePar);
    }

    private static function prepareLedgerRefunds(int $commandeId): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $order = self::lockOrder($db, $commandeId);
            self::assertCancellationPolicy($order);
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
                    throw new RuntimeException('Référence fournisseur absente pour un paiement à rembourser.');
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
                    (int) $payment['montant_cents'],
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

    private static function performLedgerProviderRefund(array $attempt): void
    {
        $provider = trim((string) ($attempt['provider'] ?? ''));
        if ($provider === '') {
            throw new RuntimeException('Fournisseur de remboursement absent.');
        }

        try {
            $refund = PaymentGatewayFactory::refundForProvider($provider)->refund(
                (string) $attempt['provider_payment_reference'],
                (int) $attempt['amount_cents'],
                (string) $attempt['operation_key'],
                [
                    'commande_id' => (string) $attempt['commande_id'],
                    'paiement_id' => (string) $attempt['paiement_id'],
                ],
            );
        } catch (Throwable $e) {
            self::updateRefundAttempt((int) $attempt['refund_attempt_id'], 'failed', null, $e->getMessage());
            throw new RuntimeException('Le remboursement fournisseur a échoué. La commande n’a pas été annulée.', 0, $e);
        }

        self::updateRefundAttempt(
            (int) $attempt['refund_attempt_id'],
            $refund->status,
            $refund->id,
            $refund->status === 'succeeded' ? null : 'Statut fournisseur: ' . $refund->status,
        );

        if ($refund->status !== 'succeeded') {
            throw new RuntimeException(
                $refund->status === 'failed'
                    ? 'Le remboursement fournisseur a échoué. La commande n’a pas été annulée.'
                    : 'Le remboursement fournisseur est encore en cours. Réessayez après confirmation.',
            );
        }
    }

    private static function refundAdditionalProviderAttempts(int $commandeId): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $order = self::lockOrder($db, $commandeId);
            self::assertCancellationPolicy($order);

            $stmt = $db->prepare(
                "SELECT pa.*
                 FROM payment_attempt pa
                 JOIN order_draft d ON d.draft_id = pa.draft_id
                 WHERE d.commande_id = ?
                   AND pa.status = 'paid'
                   AND pa.provider_payment_intent_id IS NOT NULL
                   AND NOT EXISTS (
                       SELECT 1
                       FROM paiement p
                       WHERE p.commande_id = d.commande_id
                         AND p.nature = 'encaissement'
                         AND p.reference = pa.provider_payment_intent_id
                   )
                 ORDER BY pa.attempt_id ASC
                 FOR UPDATE",
            );
            $stmt->execute([$commandeId]);
            $attempts = $stmt->fetchAll();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        foreach ($attempts as $attempt) {
            $provider = trim((string) ($attempt['provider'] ?? ''));
            $paymentReference = trim((string) ($attempt['provider_payment_intent_id'] ?? ''));
            if ($provider === '' || $paymentReference === '') {
                throw new RuntimeException('Tentative fournisseur additionnelle incohérente.');
            }

            try {
                $refund = PaymentGatewayFactory::refundForProvider($provider)->refund(
                    $paymentReference,
                    (int) $attempt['expected_amount_cents'],
                    sprintf('provider-attempt-refund:%s:%d', $provider, (int) $attempt['attempt_id']),
                    [
                        'commande_id' => (string) $commandeId,
                        'attempt_id' => (string) $attempt['attempt_id'],
                    ],
                );
            } catch (Throwable $e) {
                self::recordAdditionalAttemptRefund(
                    (int) $attempt['attempt_id'],
                    'paid',
                    null,
                    'Remboursement fournisseur additionnel échoué : ' . $e->getMessage(),
                );
                throw new RuntimeException(
                    'Un encaissement fournisseur additionnel n’a pas pu être remboursé. Annulation bloquée.',
                    0,
                    $e,
                );
            }

            if ($refund->status !== 'succeeded') {
                self::recordAdditionalAttemptRefund(
                    (int) $attempt['attempt_id'],
                    'paid',
                    $refund->id,
                    'Remboursement additionnel en statut ' . $refund->status . '.',
                );
                throw new RuntimeException('Un remboursement fournisseur additionnel n’est pas confirmé. Annulation bloquée.');
            }

            self::recordAdditionalAttemptRefund(
                (int) $attempt['attempt_id'],
                'refunded',
                $refund->id,
                null,
            );
        }
    }

    private static function recordAdditionalAttemptRefund(
        int $attemptId,
        string $status,
        ?string $providerRefundId,
        ?string $lastError,
    ): void {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE payment_attempt
             SET status = ?,
                 provider_refund_id = COALESCE(?, provider_refund_id),
                 refunded_at = CASE WHEN ? = \'refunded\' THEN NOW() ELSE refunded_at END,
                 last_error = ?
             WHERE attempt_id = ?',
        );
        $stmt->execute([
            $status,
            $providerRefundId,
            $status,
            $lastError !== null ? substr($lastError, 0, 500) : null,
            $attemptId,
        ]);
    }

    private static function updateRefundAttempt(
        int $attemptId,
        string $status,
        ?string $providerRefundId,
        ?string $lastError,
    ): void {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE payment_refund_attempt
             SET status = ?, provider_refund_id = COALESCE(?, provider_refund_id), last_error = ?
             WHERE refund_attempt_id = ?',
        );
        $stmt->execute([
            $status,
            $providerRefundId,
            $lastError !== null ? substr($lastError, 0, 500) : null,
            $attemptId,
        ]);
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
            self::assertCancellationPolicy($order);
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
                    throw new RuntimeException('Remboursement fournisseur non confirmé : annulation bloquée.');
                }
                PaymentLedgerService::appendStripeRefund($db, $payment, (string) $refundId);
            }

            $unreconciled = $db->prepare(
                "SELECT COUNT(*)
                 FROM payment_attempt pa
                 JOIN order_draft d ON d.draft_id = pa.draft_id
                 WHERE d.commande_id = ?
                   AND pa.status = 'paid'
                   AND pa.provider_payment_intent_id IS NOT NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM paiement p
                       WHERE p.commande_id = d.commande_id
                         AND p.nature = 'encaissement'
                         AND p.reference = pa.provider_payment_intent_id
                   )",
            );
            $unreconciled->execute([$commandeId]);
            if ((int) $unreconciled->fetchColumn() !== 0) {
                throw new RuntimeException('Un encaissement fournisseur additionnel reste non remboursé. Annulation bloquée.');
            }

            if (PaymentLedgerService::netCollectedCents($db, $commandeId) !== 0) {
                throw new RuntimeException('Un solde encaissé subsiste : annulation bloquée.');
            }

            InventoryLedgerService::restoreOrderConsumption($db, $commandeId, $modifiePar);
            self::restoreMenuStockOnce($db, $commandeId);
            $creditNoteIds = BillingCreditNoteService::createForCancellation($db, $commandeId, $modifiePar);

            $stmt = $db->prepare(
                'UPDATE commande
                 SET statut = ?, motif_annulation = ?, mode_contact_annulation = ?
                 WHERE commande_id = ?',
            );
            $stmt->execute([OrderStatus::cancelled(), $motif, $modeContact, $commandeId]);

            OrderStatusHistoryService::append(
                $db,
                $commandeId,
                $oldStatus,
                OrderStatus::cancelled(),
                'Annulation (' . $modeContact . ') : ' . $motif,
                $modifiePar,
            );

            $db->commit();

            foreach ($creditNoteIds as $creditNoteId) {
                try {
                    BillingDocumentStorage::ensureArchive($creditNoteId);
                } catch (Throwable $archiveError) {
                    error_log(sprintf(
                        '[facturation] avoir finalisé mais archive en échec document_id=%d: %s',
                        $creditNoteId,
                        $archiveError->getMessage(),
                    ));
                }
            }

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

    private static function assertCancellationPolicy(array $order): void
    {
        $date = trim((string) ($order['date_prestation'] ?? ''));
        $time = trim((string) ($order['heure_livraison'] ?? ''));
        if ($date === '') {
            throw new RuntimeException('Date de prestation absente : annulation financière bloquée.');
        }
        if ($time === '') {
            $time = '00:00:00';
        } elseif (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        $timezoneName = (string) (Configuration::get('market.timezone') ?? 'Europe/Paris');
        $timezone = new DateTimeZone($timezoneName);
        $serviceAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time, $timezone);
        if (!$serviceAt) {
            throw new RuntimeException('Date de prestation invalide : annulation financière bloquée.');
        }

        $policy = new BusinessPolicy(static fn(string $key): mixed => Configuration::get($key));
        try {
            $refundPercent = $policy->cancellationRefundPercent($serviceAt, new DateTimeImmutable('now', $timezone));
        } catch (\InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        if ($refundPercent !== 100) {
            throw new RuntimeException('La politique V1 exige un remboursement intégral pour toute annulation autorisée.');
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
        $stmt = $db->prepare(
            'SELECT commande_id, statut, date_prestation, heure_livraison
             FROM commande WHERE commande_id = ? FOR UPDATE',
        );
        $stmt->execute([$commandeId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Commande introuvable.');
        }
        return $order;
    }
}
