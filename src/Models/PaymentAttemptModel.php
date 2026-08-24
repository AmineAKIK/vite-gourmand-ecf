<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
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
        int $userId
    ): array {
        $numeroCommande = (string) ($commandeData['numero_commande'] ?? '');
        $expectedCents = (int) ($pricing['total_ttc_cents'] ?? 0);
        $currency = strtolower((string) ($pricing['currency'] ?? 'eur'));

        if ($numeroCommande === '' || $expectedCents <= 0 || !preg_match('/^[a-z]{3}$/', $currency)) {
            throw new RuntimeException('Draft de paiement invalide.');
        }

        try {
            $commandeJson = json_encode($commandeData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $pricingJson = json_encode($pricing, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $panierJson = json_encode($panier, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new RuntimeException('Impossible de sérialiser le draft de paiement.', 0, $e);
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO order_draft (
                    numero_commande, utilisateur_id, status, currency, expected_total_cents,
                    commande_snapshot, pricing_snapshot, panier_snapshot, expires_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))'
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
            ]);
            $draftId = (int) $db->lastInsertId();

            $attemptStmt = $db->prepare(
                'INSERT INTO payment_attempt (
                    draft_id, provider, status, expected_amount_cents, currency
                 ) VALUES (?, ?, ?, ?, ?)'
            );
            $attemptStmt->execute([$draftId, 'stripe', 'created', $expectedCents, $currency]);
            $attemptId = (int) $db->lastInsertId();

            $db->commit();

            return ['draft_id' => $draftId, 'attempt_id' => $attemptId];
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
            'SELECT * FROM order_draft WHERE draft_id = ? AND utilisateur_id = ? LIMIT 1'
        );
        $stmt->execute([$draftId, $userId]);
        $row = $stmt->fetch();

        return $row ? self::hydrateDraft($row) : null;
    }

    public static function latestAttemptForDraft(int $draftId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM payment_attempt WHERE draft_id = ? ORDER BY attempt_id DESC LIMIT 1'
        );
        $stmt->execute([$draftId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function bindStripeSession(int $attemptId, string $sessionId): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE payment_attempt
             SET provider_session_id = ?, status = 'checkout_created'
             WHERE attempt_id = ? AND provider = 'stripe'"
        );
        $stmt->execute([$sessionId, $attemptId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Tentative de paiement Stripe introuvable.');
        }
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
            "UPDATE order_draft SET commande_id = ?, status = 'consumed' WHERE draft_id = ?"
        );
        $stmt->execute([$commandeId, $draftId]);
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
