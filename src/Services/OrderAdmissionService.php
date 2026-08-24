<?php

declare(strict_types=1);

namespace App\Services;

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

        if ($maxPerDay > 0) {
            $dayCount = self::countOrdersForDay($db, $datePrestation)
                + self::countActiveReservationsForDay($db, $datePrestation);
            if ($dayCount >= $maxPerDay) {
                throw new RuntimeException(
                    'Capacité journalière atteinte (' . $maxPerDay . ' commande(s)). Choisissez une autre date.',
                );
            }
        }

        if ($maxPerMonth > 0) {
            $monthCount = self::countOrdersForMonth($db, $monthKey)
                + self::countActiveReservationsForMonth($db, $monthKey);
            if ($monthCount >= $maxPerMonth) {
                throw new RuntimeException(
                    'Quota mensuel atteint (' . $maxPerMonth . ' commandes). '
                    . 'Passez au plan supérieur pour continuer à accepter des commandes.',
                );
            }
        }

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
            'SELECT commande_id FROM order_admission_reservation WHERE numero_commande = ? FOR UPDATE',
        );
        $existing->execute([$numeroCommande]);
        $row = $existing->fetch();
        if ($row) {
            if ((int) ($row['commande_id'] ?? 0) !== $commandeId) {
                throw new RuntimeException('Réservation déjà consommée par une autre commande.');
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
