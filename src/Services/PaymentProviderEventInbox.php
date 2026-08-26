<?php

declare(strict_types=1);

namespace App\Services;

use App\Payments\PaymentProviderEvent;
use PDO;
use RuntimeException;
use Throwable;

final class PaymentProviderEventInbox
{
    public static function claim(PDO $db, PaymentProviderEvent $event): void
    {
        $occurredAt = $event->occurredAt !== null ? date('Y-m-d H:i:s', $event->occurredAt) : null;
        $stmt = $db->prepare(
            "INSERT INTO payment_provider_event (
                provider, event_id, event_type, object_id, status, occurred_at
             ) VALUES (?, ?, ?, ?, 'processing', ?)
             ON DUPLICATE KEY UPDATE event_id = VALUES(event_id)",
        );
        $stmt->execute([
            $event->provider,
            $event->id,
            $event->providerType,
            $event->objectId,
            $occurredAt,
        ]);
    }

    /** @return array<string,mixed> */
    public static function lock(PDO $db, string $provider, string $eventId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM payment_provider_event WHERE provider = ? AND event_id = ? FOR UPDATE',
        );
        $stmt->execute([$provider, $eventId]);
        $event = $stmt->fetch();
        if (!$event) {
            throw new RuntimeException('Événement de paiement introuvable après verrouillage.');
        }

        return $event;
    }

    public static function markProcessed(PDO $db, PaymentProviderEvent $event): void
    {
        self::mark($db, $event, 'processed', null, true);
    }

    public static function markIgnored(PDO $db, PaymentProviderEvent $event): void
    {
        self::mark($db, $event, 'ignored', null, true);
    }

    public static function markFailed(PDO $db, PaymentProviderEvent $event, string $message): void
    {
        self::mark($db, $event, 'failed', $message, false);
    }

    public static function recordFailure(PDO $db, PaymentProviderEvent $event, string $message): void
    {
        try {
            $occurredAt = $event->occurredAt !== null ? date('Y-m-d H:i:s', $event->occurredAt) : null;
            $stmt = $db->prepare(
                "INSERT INTO payment_provider_event (
                    provider, event_id, event_type, object_id, status, last_error, occurred_at
                 ) VALUES (?, ?, ?, ?, 'failed', ?, ?)
                 ON DUPLICATE KEY UPDATE
                    status = IF(status = 'processed', status, 'failed'),
                    last_error = IF(status = 'processed', last_error, VALUES(last_error)),
                    object_id = COALESCE(object_id, VALUES(object_id)),
                    occurred_at = COALESCE(occurred_at, VALUES(occurred_at)),
                    updated_at = CURRENT_TIMESTAMP",
            );
            $stmt->execute([
                $event->provider,
                $event->id,
                $event->providerType,
                $event->objectId,
                mb_substr($message, 0, 4000),
                $occurredAt,
            ]);
        } catch (Throwable $trackingError) {
            error_log('[payment-webhook] impossible de tracer un échec: ' . $trackingError->getMessage());
        }
    }

    private static function mark(
        PDO $db,
        PaymentProviderEvent $event,
        string $status,
        ?string $message,
        bool $processed,
    ): void {
        $stmt = $db->prepare(
            'UPDATE payment_provider_event
             SET status = ?, last_error = ?, processed_at = ?, updated_at = CURRENT_TIMESTAMP
             WHERE provider = ? AND event_id = ?',
        );
        $stmt->execute([
            $status,
            $message,
            $processed ? date('Y-m-d H:i:s') : null,
            $event->provider,
            $event->id,
        ]);
    }
}
