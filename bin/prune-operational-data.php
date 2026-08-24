<?php

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Domain\DataRetentionPolicy;
use App\Services\DataLifecycleService;

$options = getopt('', ['apply', 'notification-days::', 'reminder-days::', 'batch-size::']);
$apply = array_key_exists('apply', $options);
$notificationDays = DataRetentionPolicy::days(
    isset($options['notification-days'])
        ? (int) $options['notification-days']
        : DataRetentionPolicy::DEFAULT_READ_NOTIFICATION_DAYS,
);
$reminderDays = DataRetentionPolicy::days(
    isset($options['reminder-days'])
        ? (int) $options['reminder-days']
        : DataRetentionPolicy::DEFAULT_SENT_REMINDER_DAYS,
);
$batchSize = DataRetentionPolicy::batchSize(
    isset($options['batch-size'])
        ? (int) $options['batch-size']
        : DataRetentionPolicy::DEFAULT_BATCH_SIZE,
);

try {
    $eligible = DataLifecycleService::countEligible($notificationDays, $reminderDays);

    fwrite(STDOUT, sprintf(
        "Eligible: %d read notifications, %d sent reminder logs.\n",
        $eligible['read_notifications'],
        $eligible['sent_reminders'],
    ));

    if (!$apply) {
        fwrite(STDOUT, "Dry-run only. Re-run with --apply to delete eligible operational data.\n");
        exit(0);
    }

    $deleted = DataLifecycleService::prune($notificationDays, $reminderDays, $batchSize);
    fwrite(STDOUT, sprintf(
        "Deleted: %d read notifications, %d sent reminder logs.\n",
        $deleted['read_notifications'],
        $deleted['sent_reminders'],
    ));
    exit(0);
} catch (\Throwable $error) {
    fwrite(STDERR, 'Operational data pruning failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
