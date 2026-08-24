<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Config\PlanConfig;
use App\Config\SiteConfig;
use App\Services\OrderAdmissionService;
use JsonException;
use RuntimeException;

final class PaymentAttemptModel
{
    /**
     * @return array{draft_id:int, attempt_id:int}
     */
    public static function createDraftWithAttempt(
        array $commandeData,
        array $pricing,
        array $panier,
        int $userId,
    ): array {
        $numeroCommande = (string) ($commandeData['numero_commande'] ?? '');
        $datePrestation = (string) ($commandeData['date_prestation'] ?? '');
        $expectedCents = (int) ($pricing['total_ttc_cents'] ?? 0);
        $currency = strtolower((string) ($pricing['currency'] ?? 'eur'));

        if (
            $numeroCommande === ''
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePrestation)
            || $expectedCents <= 0
            || !preg_match('/^[a-z]{3}$/', $currency)
        ) {
            throw new RuntimeException('Draft de paiement invalide.');
        }

        try {
            $commandeJson = json_encode($commandeData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $pricingJson = json_encode($pricing, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $panierJson = json_encode($panier, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new RuntimeException('Impossible de sérialiser le draft de paiement.', 0, $e);
        }

        $expiresAt = date('Y-m-d H:i:s', time() + 7200);
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $reservationId = OrderAdmissionService::reserve(
                $db,
                $numeroCommande,
                $datePrestation,
                SiteConfig::commandesMaxParJour(),
                PlanConfig::maxCommandesMois(),
                $expiresAt,
            );

            $stmt = $db->prepare(
                'INSERT INTO order_draft (
                    numero_commande, utilisateur_id, status, currency, expected_total_cents,
                    commande_snapshot, pricing_snapshot, panier_snapshot, expires_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $stmt->execute([
                $numeroCommande,
                $userId,
                'pending_payment',
                $currency,
                $expectedCents,
                $commandeJson,
                $pricingJson,
                $panierJson,
                $expiresAt,
            ]);
            $draftId = (int) $db->lastInsertId();
            OrderAdmissionService::attachDraft($db, $reservationId, $draftId);
            $attemptId = self::insertAttempt($db, $draftId, $expectedCents, $currency);

            $db->commit();

            return ['draft_id' => $draftId, 'attempt_id' => $attemptId];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function createRetryAttempt(int $draftId): int
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                "SELECT expected_total_cents, currency
                 FROM order_draft
                 WHERE draft_id = ? AND status = 'pending_payment'
                 FOR UPDATE",
            );
            $stmt->execute([$draftId]);
            $draft = $stmt->fetch();

            if (!$draft) {
                throw new RuntimeException('Draft indisponible pour une nouvelle tentative.');
            }

            $attemptId = self::insertAttempt(
                $db,
                $draftId,
                (int) $draft['expected_total_cents'],
                strtolower((string) $draft['currency']),
            );
            $db->commit();

            return $attemptId;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function findDraftForUser(int $draftId, int $userId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM order_draft WHERE draft_id = ? AND utilisateur_id = ? LIMIT 1',
        );
        $stmt->execute([$draftId, $userId]);
        $row = $stmt->fetch();

        return $row ? self::hydrateDraft($row) : null;
    }

    /**
     * Resolve a persisted Stripe attempt from the provider session id and the authenticated owner.
     *
     * @return array{draft:array, attempt:array}|null
     */
    public static function findStripeContextForUser(string $sessionId, int $userId): ?array
    {
        if ($sessionId === '' || $userId <= 0) {
            return null;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT pa.attempt_id, pa.draft_id
             FROM payment_attempt pa
             JOIN order_draft od ON od.draft_id = pa.draft_id
             WHERE pa.provider = 'stripe'
               AND pa.provider_session_id = ?
               AND od.utilisateur_id = ?
             LIMIT 1",
        );
        $stmt->execute([$sessionId, $userId]);
        $ids = $stmt->fetch();
        if (!$ids) {
            return null;
        }

        $draft = self::findDraftForUser((int) $ids['draft_id'], $userId);
        $attempt = self::findAttemptForDraft((int) $ids['attempt_id'], (int) $ids['draft_id']);
        if (!$draft || !$attempt) {
            return null;
        }

        return ['draft' => $draft, 'attempt' => $attempt];
    }

    public static function findAttemptForDraft(int $attemptId, int $draftId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM payment_attempt WHERE attempt_id = ? AND draft_id = ? LIMIT 1',
        );
        $stmt->execute([$attemptId, $draftId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function latestAttemptForDraft(int $draftId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM payment_attempt WHERE draft_id = ? ORDER BY attempt_id DESC LIMIT 1',
        );
        $stmt->execute([$draftId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function bindStripeSession(int $attemptId, string $sessionId): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE payment_attempt
             SET provider_session_id = ?, status = 'checkout_created', last_error = NULL
             WHERE attempt_id = ? AND provider = 'stripe'",
        );
        $stmt->execute([$sessionId, $attemptId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Tentative de paiement Stripe introuvable.');
        }
    }

    public static function markAttemptStatus(int $attemptId, string $status, ?string $paymentIntentId = null): void
    {
        $allowed = ['created', 'checkout_created', 'paid', 'cancelled', 'failed'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Statut de tentative invalide.');
        }

        $stmt = Database::getConnection()->prepare(
            'UPDATE payment_attempt
             SET status = ?, provider_payment_intent_id = COALESCE(?, provider_payment_intent_id)
             WHERE attempt_id = ?',
        );
        $stmt->execute([$status, $paymentIntentId, $attemptId]);
    }

    public static function recordAttemptError(int $attemptId, string $message): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE payment_attempt SET last_error = ? WHERE attempt_id = ?',
        );
        $stmt->execute([mb_substr($message, 0, 4000), $attemptId]);
    }

    public static function failAttempt(int $attemptId, string $message): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE payment_attempt SET status = 'failed', last_error = ? WHERE attempt_id = ?",
        );
        $stmt->execute([mb_substr($message, 0, 4000), $attemptId]);
    }

    public static function markDraftStatus(int $draftId, string $status): void
    {
        $allowed = ['pending_payment', 'paid', 'cancelled', 'failed', 'consumed'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Statut de draft invalide.');
        }

        $stmt = Database::getConnection()->prepare('UPDATE order_draft SET status = ? WHERE draft_id = ?');
        $stmt->execute([$status, $draftId]);
    }

    public static function attachCommande(int $draftId, int $commandeId): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE order_draft SET commande_id = ?, status = 'consumed' WHERE draft_id = ?",
        );
        $stmt->execute([$commandeId, $draftId]);
    }

    private static function insertAttempt(\PDO $db, int $draftId, int $expectedCents, string $currency): int
    {
        $attemptStmt = $db->prepare(
            'INSERT INTO payment_attempt (
                draft_id, provider, status, expected_amount_cents, currency
             ) VALUES (?, ?, ?, ?, ?)',
        );
        $attemptStmt->execute([$draftId, 'stripe', 'created', $expectedCents, $currency]);

        return (int) $db->lastInsertId();
    }

    private static function hydrateDraft(array $row): array
    {
        try {
            $row['commande_data'] = json_decode((string) $row['commande_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $row['pricing'] = json_decode((string) $row['pricing_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $row['panier'] = json_decode((string) $row['panier_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Snapshot de paiement illisible.', 0, $e);
        }

        return $row;
    }
}
