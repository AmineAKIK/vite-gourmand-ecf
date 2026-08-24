<?php

namespace App\Services;

use App\Config\Database;
use App\Domain\ReminderLeasePolicy;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class ReminderLeaseService
{
    private const LEASE_SECONDS = 300;

    public static function claim(int $commandeId, string $typeRappel, string $dateCible): ?string
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $select = $db->prepare(
                'SELECT rappel_id, sent_at, lease_until FROM cron_rappel_log
                 WHERE commande_id = ? AND type_rappel = ? AND date_cible = ?
                 FOR UPDATE'
            );
            $select->execute([$commandeId, $typeRappel, $dateCible]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            $now = new DateTimeImmutable('now');

            if ($row && !ReminderLeasePolicy::canClaim($row['sent_at'] ?? null, $row['lease_until'] ?? null, $now)) {
                $db->commit();

                return null;
            }

            $token = bin2hex(random_bytes(16));
            $leaseUntil = $now->modify('+' . self::LEASE_SECONDS . ' seconds')->format('Y-m-d H:i:s');

            if ($row) {
                $update = $db->prepare(
                    'UPDATE cron_rappel_log
                     SET lease_token = ?, lease_until = ?, attempt_count = attempt_count + 1, last_error = NULL
                     WHERE rappel_id = ?'
                );
                $update->execute([$token, $leaseUntil, (int) $row['rappel_id']]);
            } else {
                $insert = $db->prepare(
                    'INSERT INTO cron_rappel_log
                     (commande_id, type_rappel, date_cible, lease_token, lease_until, attempt_count)
                     VALUES (?, ?, ?, ?, ?, 1)'
                );
                $insert->execute([$commandeId, $typeRappel, $dateCible, $token, $leaseUntil]);
            }

            $db->commit();

            return $token;
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $error;
        }
    }

    public static function markSent(int $commandeId, string $typeRappel, string $dateCible, string $leaseToken): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE cron_rappel_log
             SET sent_at = NOW(), lease_token = NULL, lease_until = NULL, last_error = NULL
             WHERE commande_id = ? AND type_rappel = ? AND date_cible = ?
               AND sent_at IS NULL AND lease_token = ?'
        );
        $stmt->execute([$commandeId, $typeRappel, $dateCible, $leaseToken]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Le lease du rappel a expiré ou changé avant confirmation.');
        }
    }

    public static function markFailed(
        int $commandeId,
        string $typeRappel,
        string $dateCible,
        string $leaseToken,
        \Throwable $error
    ): void {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE cron_rappel_log
             SET lease_token = NULL, lease_until = NULL, last_error = ?
             WHERE commande_id = ? AND type_rappel = ? AND date_cible = ?
               AND sent_at IS NULL AND lease_token = ?'
        );
        $stmt->execute([
            ReminderLeasePolicy::errorMessage($error),
            $commandeId,
            $typeRappel,
            $dateCible,
            $leaseToken,
        ]);
    }
}
