<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\OrderStatus;
use App\Domain\PaymentLedgerPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class PaymentLedgerService
{
    public static function recordCollection(PDO $db, array $data, ?int $creePar): int
    {
        self::requireTransaction($db);

        $commandeId = (int) ($data['commande_id'] ?? 0);
        $type = (string) ($data['type_paiement'] ?? '');
        $amountCents = self::canonicalCents($data['montant_cents'] ?? null);
        $mode = trim((string) ($data['mode'] ?? ''));
        $date = trim((string) ($data['date_paiement'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? '')) ?: null;
        $note = trim((string) ($data['note'] ?? '')) ?: null;
        $documentId = !empty($data['document_id']) ? (int) $data['document_id'] : null;

        if ($commandeId <= 0 || !in_array($type, ['acompte', 'solde', 'paiement_unique'], true) || $mode === '' || $date === '') {
            throw new InvalidArgumentException('Champs paiement obligatoires manquants.');
        }
        if (!DateTimeImmutable::createFromFormat('!Y-m-d', $date)) {
            throw new InvalidArgumentException('Date de paiement invalide.');
        }

        $commande = self::lockOrder($db, $commandeId);
        if ((string) $commande['statut'] === OrderStatus::cancelled()) {
            throw new RuntimeException('Impossible d’enregistrer un paiement sur une commande annulée.');
        }
        $netCents = self::netCollectedCents($db, $commandeId);
        PaymentLedgerPolicy::assertCollectionAmount($amountCents, $netCents, (int) $commande['prix_total_cents']);

        if ($documentId !== null) {
            $doc = $db->prepare('SELECT commande_id FROM document_facturation WHERE document_id = ?');
            $doc->execute([$documentId]);
            $docCommandeId = $doc->fetchColumn();
            if ($docCommandeId === false || (int) $docCommandeId !== $commandeId) {
                throw new RuntimeException('Le document de facturation ne correspond pas à cette commande.');
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO paiement
                (commande_id, document_id, type_paiement, nature, montant_cents, mode, date_paiement, reference, note, cree_par)
             VALUES (?, ?, ?, 'encaissement', ?, ?, ?, ?, ?, ?)",
        );
        $stmt->execute([
            $commandeId,
            $documentId,
            $type,
            $amountCents,
            $mode,
            $date,
            $reference,
            $note,
            $creePar,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function reverseManualCollection(PDO $db, int $paiementId, ?int $creePar): int
    {
        self::requireTransaction($db);
        if ($paiementId <= 0) {
            throw new RuntimeException('Paiement invalide.');
        }

        $stmt = $db->prepare('SELECT * FROM paiement WHERE paiement_id = ? FOR UPDATE');
        $stmt->execute([$paiementId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            throw new RuntimeException('Paiement introuvable.');
        }
        if ((string) ($payment['nature'] ?? 'encaissement') !== 'encaissement') {
            throw new RuntimeException('Seul un encaissement peut être contre-passé.');
        }
        if ((string) $payment['mode'] === 'cb_online') {
            throw new RuntimeException('Un paiement Stripe doit être remboursé via le workflow d’annulation.');
        }

        self::lockOrder($db, (int) $payment['commande_id']);

        return self::appendRefund(
            $db,
            $payment,
            PaymentLedgerPolicy::collectionOperationKey($paiementId),
            'Contre-passation du paiement #' . $paiementId,
            $creePar,
            $payment['reference'] !== null ? (string) $payment['reference'] : null,
        );
    }

    public static function assertOrderEditable(PDO $db, int $commandeId): void
    {
        self::requireTransaction($db);
        self::lockOrder($db, $commandeId);
        PaymentLedgerPolicy::assertOrderEditable(self::netCollectedCents($db, $commandeId));
    }

    public static function netCollectedCents(PDO $db, int $commandeId): int
    {
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN nature = 'remboursement' THEN -montant_cents ELSE montant_cents END), 0)
             FROM paiement WHERE commande_id = ?",
        );
        $stmt->execute([$commandeId]);

        return (int) $stmt->fetchColumn();
    }

    public static function netManualCollectedCents(PDO $db, int $commandeId): int
    {
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN nature = 'remboursement' THEN -montant_cents ELSE montant_cents END), 0)
             FROM paiement WHERE commande_id = ? AND mode <> 'cb_online'",
        );
        $stmt->execute([$commandeId]);

        return (int) $stmt->fetchColumn();
    }

    public static function stripeCollectionsForOrder(PDO $db, int $commandeId): array
    {
        $stmt = $db->prepare(
            "SELECT p.*, CAST(p.montant_cents AS DECIMAL(20,2)) / 100 AS montant
             FROM paiement p
             WHERE p.commande_id = ?
               AND p.mode = 'cb_online'
               AND p.nature = 'encaissement'
               AND NOT EXISTS (
                   SELECT 1 FROM paiement r WHERE r.reversal_of_paiement_id = p.paiement_id
               )
             ORDER BY p.paiement_id ASC
             FOR UPDATE",
        );
        $stmt->execute([$commandeId]);

        return $stmt->fetchAll();
    }

    public static function appendStripeRefund(PDO $db, array $payment, string $providerRefundId): int
    {
        self::requireTransaction($db);

        return self::appendRefund(
            $db,
            $payment,
            PaymentLedgerPolicy::stripeRefundOperationKey((int) $payment['paiement_id']),
            'Remboursement Stripe du paiement #' . (int) $payment['paiement_id'],
            null,
            $providerRefundId,
        );
    }

    private static function appendRefund(
        PDO $db,
        array $payment,
        string $operationKey,
        string $note,
        ?int $creePar,
        ?string $reference,
    ): int {
        $stmt = $db->prepare(
            "INSERT INTO paiement
                (commande_id, document_id, type_paiement, nature, montant_cents, mode, date_paiement,
                 reference, note, operation_key, reversal_of_paiement_id, cree_par)
             VALUES (?, ?, ?, 'remboursement', ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE paiement_id = LAST_INSERT_ID(paiement_id)",
        );
        $stmt->execute([
            (int) $payment['commande_id'],
            !empty($payment['document_id']) ? (int) $payment['document_id'] : null,
            (string) $payment['type_paiement'],
            self::canonicalCents($payment['montant_cents'] ?? null),
            (string) $payment['mode'],
            date('Y-m-d'),
            $reference,
            $note,
            $operationKey,
            (int) $payment['paiement_id'],
            $creePar,
        ]);
        $refundId = (int) $db->lastInsertId();

        $verify = $db->prepare(
            'SELECT commande_id, nature, montant_cents, mode, operation_key, reversal_of_paiement_id
             FROM paiement WHERE paiement_id = ?',
        );
        $verify->execute([$refundId]);
        $existing = $verify->fetch();
        if (!$existing
            || (int) $existing['commande_id'] !== (int) $payment['commande_id']
            || (string) $existing['nature'] !== 'remboursement'
            || self::canonicalCents($existing['montant_cents'] ?? null) !== self::canonicalCents($payment['montant_cents'] ?? null)
            || (string) $existing['mode'] !== (string) $payment['mode']
            || (string) $existing['operation_key'] !== $operationKey
            || (int) $existing['reversal_of_paiement_id'] !== (int) $payment['paiement_id']
        ) {
            throw new RuntimeException('Collision de clé idempotente du ledger de paiement.');
        }

        return $refundId;
    }

    private static function canonicalCents(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('Montant de paiement en centimes invalide.');
        }

        return (int) $value;
    }

    private static function lockOrder(PDO $db, int $commandeId): array
    {
        $stmt = $db->prepare('SELECT commande_id, prix_total_cents, statut FROM commande WHERE commande_id = ? FOR UPDATE');
        $stmt->execute([$commandeId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Commande introuvable.');
        }

        return $order;
    }

    private static function requireTransaction(PDO $db): void
    {
        if (!$db->inTransaction()) {
            throw new RuntimeException('Le ledger de paiement doit être modifié dans une transaction.');
        }
    }
}
