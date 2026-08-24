<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\OrderAdmissionPolicy;
use App\Domain\OrderStatus;
use PDO;
use RuntimeException;

final class OrderAdmissionService
{
    public static function reserve(
        PDO $db,
        string $numeroCommande,
        string $datePrestation,
        int $maxPerDay,
        int $maxPerMonth,
        ?string $expiresAt = null,
    ): int {
        if ($numeroCommande === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePrestation)) {
            throw new RuntimeException('Données d’admission de commande invalides.');
        }
        if (!$db->inTransaction()) {
            throw new RuntimeException('L’admission doit être exécutée dans une transaction.');
        }

        $monthKey = date('Y-m');
        self::lockScopes($db, ['day:' . $datePrestation, 'month:' . $monthKey]);

        $existing = self::findReservationForUpdate($db, $numeroCommande);
        if ($existing) {
            if ((string) $existing['date_prestation'] !== $datePrestation) {
                throw new RuntimeException('Réservation de capacité incohérente.');
            }

            return (int) $existing['reservation_id'];
        }

        self::expireStaleReservations($db, $datePrestation, $monthKey);

        $dayCount = $maxPerDay > 0
            ? self::countOrdersForDay($db, $datePrestation) + self::countActiveReservationsForDay($db, $datePrestation)
            : 0;
        $monthCount = $maxPerMonth > 0
            ? self::countOrdersForMonth($db, $monthKey) + self::countActiveReservationsForMonth($db, $monthKey)
            : 0;

        OrderAdmissionPolicy::assertWithinLimits($dayCount, $maxPerDay, $monthCount, $maxPerMonth);

        $stmt = $db->prepare(
            "INSERT INTO order_admission_reservation
                (numero_commande, date_prestation, month_key, status, expires_at)
             VALUES (?, ?, ?, 'reserved', ?)",
        );
        $stmt->execute([$numeroCommande, $datePrestation, $monthKey, $expiresAt]);

        return (int) $db->lastInsertId();
    }

    public static function attachDraft(PDO $db, int $reservationId, int $draftId): void
    {
        $stmt = $db->prepare(
            "UPDATE order_admission_reservation
             SET draft_id = ?
             WHERE reservation_id = ? AND status = 'reserved' AND draft_id IS NULL",
        );
        $stmt->execute([$draftId, $reservationId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Impossible de rattacher la réservation au draft.');
        }
    }

    public static function consume(PDO $db, string $numeroCommande, int $commandeId, string $datePrestation): void
    {
        if (!$db->inTransaction()) {
            throw new RuntimeException('La consommation d’admission doit être transactionnelle.');
        }

        $stmt = $db->prepare(
            "UPDATE order_admission_reservation
             SET status = 'consumed', commande_id = ?, expires_at = NULL
             WHERE numero_commande = ? AND status = 'reserved'",
        );
        $stmt->execute([$commandeId, $numeroCommande]);
        if ($stmt->rowCount() === 1) {
            return;
        }

        $existing = $db->prepare(
            'SELECT reservation_id, commande_id FROM order_admission_reservation WHERE numero_commande = ? FOR UPDATE',
        );
        $existing->execute([$numeroCommande]);
        $row = $existing->fetch();
        if ($row) {
            $linkedCommandeId = (int) ($row['commande_id'] ?? 0);
            if ($linkedCommandeId > 0 && $linkedCommandeId !== $commandeId) {
                throw new RuntimeException('Réservation déjà consommée par une autre commande.');
            }
            if ($linkedCommandeId === $commandeId) {
                return;
            }

            // Un webhook payé peut arriver après expiration locale de la réservation.
            // Le paiement autoritatif prime : on consomme le permit historique sans
            // refaire un contrôle qui pourrait laisser un paiement sans commande.
            $consumeExisting = $db->prepare(
                "UPDATE order_admission_reservation
                 SET status = 'consumed', commande_id = ?, expires_at = NULL
                 WHERE reservation_id = ? AND commande_id IS NULL",
            );
            $consumeExisting->execute([$commandeId, (int) $row['reservation_id']]);
            if ($consumeExisting->rowCount() !== 1) {
                throw new RuntimeException('Impossible de consommer la réservation existante.');
            }
            return;
        }

        // Compatibilité pour un draft créé avant PR10 : un paiement déjà confirmé
        // ne doit jamais être rejeté faute de réservation historique.
        $insert = $db->prepare(
            "INSERT INTO order_admission_reservation
                (numero_commande, date_prestation, month_key, status, commande_id, expires_at)
             VALUES (?, ?, ?, 'consumed', ?, NULL)",
        );
        $insert->execute([$numeroCommande, $datePrestation, date('Y-m'), $commandeId]);
    }

    public static function release(PDO $db, string $numeroCommande): void
    {
        if (!$db->inTransaction()) {
            throw new RuntimeException('La libération d’admission doit être transactionnelle.');
        }

        $stmt = $db->prepare(
            "UPDATE order_admission_reservation
             SET status = 'released'
             WHERE numero_commande = ? AND status = 'reserved'",
        );
        $stmt->execute([$numeroCommande]);
    }

    public static function assertAndRecordDateMove(
        PDO $db,
        int $commandeId,
        string $targetDate,
        int $maxPerDay,
    ): void {
        if ($commandeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new RuntimeException('Modification de date invalide.');
        }
        if (!$db->inTransaction()) {
            throw new RuntimeException('La modification de date doit être transactionnelle.');
        }

        $commandeStmt = $db->prepare(
            'SELECT date_prestation, statut FROM commande WHERE commande_id = ? FOR UPDATE',
        );
        $commandeStmt->execute([$commandeId]);
        $commande = $commandeStmt->fetch();
        if (!$commande) {
            throw new RuntimeException('Commande introuvable.');
        }
        if ((string) $commande['statut'] !== OrderStatus::initial()) {
            throw new RuntimeException('Cette commande ne peut plus être modifiée.');
        }

        $currentDate = (string) $commande['date_prestation'];
        if ($currentDate === $targetDate) {
            return;
        }

        self::lockScopes($db, ['day:' . $currentDate, 'day:' . $targetDate]);
        self::expireStaleReservations($db, $targetDate, date('Y-m'));

        if ($maxPerDay > 0) {
            $ordersStmt = $db->prepare(
                'SELECT COUNT(*) FROM commande '
                . 'WHERE date_prestation = ? AND statut <> ? AND commande_id <> ?',
            );
            $ordersStmt->execute([$targetDate, OrderStatus::cancelled(), $commandeId]);
            $destinationCount = (int) $ordersStmt->fetchColumn()
                + self::countActiveReservationsForDay($db, $targetDate);

            OrderAdmissionPolicy::assertWithinLimits($destinationCount, $maxPerDay, 0, 0);
        }

        $reservationStmt = $db->prepare(
            "UPDATE order_admission_reservation
             SET date_prestation = ?
             WHERE commande_id = ? AND status = 'consumed'",
        );
        $reservationStmt->execute([$targetDate, $commandeId]);
    }

    public static function countCommittedAndReservedForDay(PDO $db, string $datePrestation): int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePrestation)) {
            return 0;
        }

        return self::countOrdersForDay($db, $datePrestation)
            + self::countActiveReservationsForDay($db, $datePrestation);
    }

    private static function lockScopes(PDO $db, array $scopeKeys): void
    {
        $scopeKeys = array_values(array_unique($scopeKeys));
        sort($scopeKeys, SORT_STRING);
        $insert = $db->prepare(
            'INSERT INTO order_admission_lock (scope_key) VALUES (?) '
            . 'ON DUPLICATE KEY UPDATE scope_key = VALUES(scope_key)',
        );
        $lock = $db->prepare('SELECT scope_key FROM order_admission_lock WHERE scope_key = ? FOR UPDATE');

        foreach ($scopeKeys as $scopeKey) {
            $insert->execute([$scopeKey]);
            $lock->execute([$scopeKey]);
            if ($lock->fetchColumn() === false) {
                throw new RuntimeException('Verrou d’admission indisponible.');
            }
        }
    }

    private static function findReservationForUpdate(PDO $db, string $numeroCommande): ?array
    {
        $stmt = $db->prepare(
            'SELECT * FROM order_admission_reservation WHERE numero_commande = ? FOR UPDATE',
        );
        $stmt->execute([$numeroCommande]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private static function expireStaleReservations(PDO $db, string $datePrestation, string $monthKey): void
    {
        $stmt = $db->prepare(
            "UPDATE order_admission_reservation
             SET status = 'expired'
             WHERE status = 'reserved'
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()
               AND (date_prestation = ? OR month_key = ?)",
        );
        $stmt->execute([$datePrestation, $monthKey]);
    }

    private static function countOrdersForDay(PDO $db, string $datePrestation): int
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM commande WHERE date_prestation = ? AND statut <> ?');
        $stmt->execute([$datePrestation, OrderStatus::cancelled()]);

        return (int) $stmt->fetchColumn();
    }

    private static function countActiveReservationsForDay(PDO $db, string $datePrestation): int
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM order_admission_reservation
             WHERE date_prestation = ? AND status = 'reserved'
               AND (expires_at IS NULL OR expires_at > NOW())",
        );
        $stmt->execute([$datePrestation]);

        return (int) $stmt->fetchColumn();
    }

    private static function countOrdersForMonth(PDO $db, string $monthKey): int
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM commande
             WHERE date_commande >= CONCAT(?, '-01')
               AND date_commande < DATE_ADD(CONCAT(?, '-01'), INTERVAL 1 MONTH)
               AND statut <> ?",
        );
        $stmt->execute([$monthKey, $monthKey, OrderStatus::cancelled()]);

        return (int) $stmt->fetchColumn();
    }

    private static function countActiveReservationsForMonth(PDO $db, string $monthKey): int
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM order_admission_reservation
             WHERE month_key = ? AND status = 'reserved'
               AND (expires_at IS NULL OR expires_at > NOW())",
        );
        $stmt->execute([$monthKey]);

        return (int) $stmt->fetchColumn();
    }
}
