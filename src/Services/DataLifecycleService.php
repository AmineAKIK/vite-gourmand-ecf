<?php

namespace App\Services;

use App\Config\Database;
use App\Domain\DataRetentionPolicy;
use DateTimeImmutable;

final class DataLifecycleService
{
    /** @return array{read_notifications:int,sent_reminders:int} */
    public static function countEligible(int $notificationDays, int $reminderDays): array
    {
        $notificationCutoff = self::cutoff($notificationDays);
        $reminderCutoff = self::cutoff($reminderDays);
        $db = Database::getConnection();

        $notifications = $db->prepare(
            'SELECT COUNT(*) FROM notification WHERE lu = 1 AND created_at < ?',
        );
        $notifications->execute([$notificationCutoff]);

        $reminders = $db->prepare(
            'SELECT COUNT(*) FROM cron_rappel_log WHERE sent_at IS NOT NULL AND sent_at < ?',
        );
        $reminders->execute([$reminderCutoff]);

        return [
            'read_notifications' => (int) $notifications->fetchColumn(),
            'sent_reminders' => (int) $reminders->fetchColumn(),
        ];
    }

    /** @return array{read_notifications:int,sent_reminders:int} */
    public static function prune(
        int $notificationDays,
        int $reminderDays,
        int $batchSize = DataRetentionPolicy::DEFAULT_BATCH_SIZE,
    ): array {
        $notificationCutoff = self::cutoff($notificationDays);
        $reminderCutoff = self::cutoff($reminderDays);
        $batchSize = DataRetentionPolicy::batchSize($batchSize);

        return [
            'read_notifications' => self::deleteInBatches(
                'DELETE FROM notification WHERE lu = 1 AND created_at < ? LIMIT ' . $batchSize,
                $notificationCutoff,
            ),
            'sent_reminders' => self::deleteInBatches(
                'DELETE FROM cron_rappel_log WHERE sent_at IS NOT NULL AND sent_at < ? LIMIT ' . $batchSize,
                $reminderCutoff,
            ),
        ];
    }

    private static function cutoff(int $days): string
    {
        $days = DataRetentionPolicy::days($days);

        return (new DateTimeImmutable('now'))
            ->modify('-' . $days . ' days')
            ->format('Y-m-d H:i:s');
    }

    private static function deleteInBatches(string $sql, string $cutoff): int
    {
        $db = Database::getConnection();
        $deleted = 0;

        do {
            $stmt = $db->prepare($sql);
            $stmt->execute([$cutoff]);
            $batch = $stmt->rowCount();
            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
