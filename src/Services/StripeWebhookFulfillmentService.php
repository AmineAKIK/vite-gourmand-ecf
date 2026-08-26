<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\OrderStatus;
use App\Domain\StripeWebhookContract;
use App\Models\CommandeModel;
use App\Models\PaiementModel;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class StripeWebhookFulfillmentService
{
    /**
     * @return array{processed:bool, duplicate:bool, commande_id:?int, commande_data:?array, panier:?array}
     */
    public static function fulfillCheckoutSessionCompleted(string $eventId, array $session): array
    {
        if ($eventId === '' || (string) ($session['id'] ?? '') === '') {
            throw new RuntimeException('Événement Stripe invalide.');
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $draftId = (int) ($metadata['draft_id'] ?? 0);
        $attemptId = (int) ($metadata['attempt_id'] ?? 0);

        if ($draftId <= 0 || $attemptId <= 0) {
            return [
                'processed' => false,
                'duplicate' => false,
                'commande_id' => null,
                'commande_data' => null,
                'panier' => null,
            ];
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            self::claimEvent($db, $eventId, 'checkout.session.completed', (string) $session['id']);

            $event = self::lockEvent($db, $eventId);
            if (($event['status'] ?? '') === 'processed') {
                $db->commit();

                return [
                    'processed' => true,
                    'duplicate' => true,
                    'commande_id' => null,
                    'commande_data' => null,
                    'panier' => null,
                ];
            }

            [$draft, $attempt] = self::lockDraftAndAttempt($db, $draftId, $attemptId);
            $validated = StripeWebhookContract::assertPaidSession($session, $draft, $attempt);

            if (!empty($draft['commande_id'])) {
                self::markEventProcessed($db, $eventId);
                $db->commit();

                return [
                    'processed' => true,
                    'duplicate' => true,
                    'commande_id' => (int) $draft['commande_id'],
                    'commande_data' => null,
                    'panier' => null,
                ];
            }

            [$commandeData, $pricing, $panier] = self::decodeSnapshots($draft);
            $commandeData['prix_total_cents'] = (int) $validated['amount_total'];
            if (($commandeData['payment_method_code'] ?? '') !== 'cb_online') {
                throw new RuntimeException('Le draft Stripe ne porte pas le moyen de paiement CB attendu.');
            }

            $commandeId = self::createCommande($db, $commandeData, $pricing['lignes'] ?? []);
            OrderAdmissionService::consume(
                $db,
                (string) $commandeData['numero_commande'],
                $commandeId,
                (string) $commandeData['date_prestation'],
            );

            PaiementModel::create([
                'commande_id' => $commandeId,
                'type_paiement' => 'paiement_unique',
                'montant_cents' => (int) $validated['amount_total'],
                'mode' => 'cb_online',
                'date_paiement' => date('Y-m-d'),
                'reference' => $validated['payment_intent'] ?? (string) $session['id'],
                'note' => 'Paiement Stripe autoritatif — session ' . (string) $session['id'],
            ], null);

            InventoryLedgerService::consumeOrder($db, $commandeId, null);

            $attemptStmt = $db->prepare(
                "UPDATE payment_attempt
                 SET status = 'paid', provider_payment_intent_id = COALESCE(?, provider_payment_intent_id), last_error = NULL
                 WHERE attempt_id = ? AND draft_id = ?",
            );
            $attemptStmt->execute([$validated['payment_intent'], $attemptId, $draftId]);

            $draftStmt = $db->prepare(
                "UPDATE order_draft
                 SET commande_id = ?, status = 'consumed'
                 WHERE draft_id = ? AND commande_id IS NULL",
            );
            $draftStmt->execute([$commandeId, $draftId]);
            if ($draftStmt->rowCount() !== 1) {
                throw new RuntimeException('Le draft a déjà été consommé.');
            }

            self::markEventProcessed($db, $eventId);
            $db->commit();

            return [
                'processed' => true,
                'duplicate' => false,
                'commande_id' => $commandeId,
                'commande_data' => $commandeData,
                'panier' => $panier,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            self::recordEventFailure($eventId, 'checkout.session.completed', (string) $session['id'], $e->getMessage());
            throw $e;
        }
    }

    private static function claimEvent(PDO $db, string $eventId, string $eventType, string $objectId): void
    {
        $stmt = $db->prepare(
            "INSERT INTO stripe_webhook_event (event_id, event_type, object_id, status)
             VALUES (?, ?, ?, 'processing')
             ON DUPLICATE KEY UPDATE event_id = VALUES(event_id)",
        );
        $stmt->execute([$eventId, $eventType, $objectId]);
    }

    private static function lockEvent(PDO $db, string $eventId): array
    {
        $stmt = $db->prepare('SELECT * FROM stripe_webhook_event WHERE event_id = ? FOR UPDATE');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        if (!$event) {
            throw new RuntimeException('Événement Stripe introuvable après verrouillage.');
        }

        return $event;
    }

    /**
     * @return array{0:array, 1:array}
     */
    private static function lockDraftAndAttempt(PDO $db, int $draftId, int $attemptId): array
    {
        $draftStmt = $db->prepare('SELECT * FROM order_draft WHERE draft_id = ? FOR UPDATE');
        $draftStmt->execute([$draftId]);
        $draft = $draftStmt->fetch();
        if (!$draft) {
            throw new RuntimeException('Draft Stripe introuvable.');
        }

        $attemptStmt = $db->prepare(
            'SELECT * FROM payment_attempt WHERE attempt_id = ? AND draft_id = ? FOR UPDATE',
        );
        $attemptStmt->execute([$attemptId, $draftId]);
        $attempt = $attemptStmt->fetch();
        if (!$attempt) {
            throw new RuntimeException('Tentative Stripe introuvable.');
        }

        return [$draft, $attempt];
    }

    /**
     * @return array{0:array, 1:array, 2:array}
     */
    private static function decodeSnapshots(array $draft): array
    {
        try {
            $commandeData = json_decode((string) $draft['commande_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $pricing = json_decode((string) $draft['pricing_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $panier = json_decode((string) $draft['panier_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Snapshots Stripe illisibles.', 0, $e);
        }

        if (!is_array($commandeData) || !is_array($pricing) || !is_array($panier)) {
            throw new RuntimeException('Snapshots Stripe invalides.');
        }
        if (!isset($pricing['lignes']) || !is_array($pricing['lignes']) || $pricing['lignes'] === []) {
            throw new RuntimeException('Lignes de pricing absentes du draft.');
        }

        return [$commandeData, $pricing, $panier];
    }

    private static function createCommande(PDO $db, array $commandeData, array $lignes): int
    {
        foreach ($lignes as $ligne) {
            $stockStmt = $db->prepare(
                'SELECT quantite_restante FROM menu WHERE menu_id = ? AND actif = 1 FOR UPDATE',
            );
            $stockStmt->execute([(int) $ligne['menu_id']]);
            $stock = $stockStmt->fetchColumn();
            if ($stock === false || ($stock !== null && (int) $stock <= 0)) {
                throw new RuntimeException('Stock indisponible pour un menu payé.');
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO commande (
                numero_commande, utilisateur_id, date_prestation, heure_livraison,
                adresse_livraison, ville_livraison, code_postal_livraison,
                prix_total_cents, currency, payment_method_code, instructions
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $commandeData['numero_commande'],
            $commandeData['utilisateur_id'],
            $commandeData['date_prestation'],
            $commandeData['heure_livraison'],
            $commandeData['adresse_livraison'],
            $commandeData['ville_livraison'],
            $commandeData['code_postal_livraison'],
            (int) $commandeData['prix_total_cents'],
            (string) $commandeData['currency'],
            (string) $commandeData['payment_method_code'],
            $commandeData['instructions'] ?? null,
        ]);
        $commandeId = (int) $db->lastInsertId();

        $ligneStmt = $db->prepare(
            'INSERT INTO commande_ligne (
                commande_id, menu_id, nombre_personne, prix_menu_cents, prix_livraison_cents,
                prix_total_ligne_cents, prix_par_personne_snapshot_cents,
                taux_tva_menu_basis_points, taux_tva_livraison_basis_points,
                taux_reduction_basis_points, remise_appliquee_cents,
                taux_tva_menu_id, taux_tva_livraison_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );

        foreach ($lignes as $ligne) {
            $ligneStmt->execute([
                $commandeId,
                (int) $ligne['menu_id'],
                (int) $ligne['nombre_personne'],
                (int) $ligne['prix_menu_cents'],
                (int) $ligne['prix_livraison_cents'],
                (int) $ligne['prix_total_ligne_cents'],
                (int) $ligne['prix_par_personne_snapshot_cents'],
                (int) $ligne['taux_tva_menu_basis_points'],
                (int) $ligne['taux_tva_livraison_basis_points'],
                (int) $ligne['taux_reduction_basis_points'],
                (int) $ligne['remise_appliquee_cents'],
                isset($ligne['taux_tva_menu_id']) ? (int) $ligne['taux_tva_menu_id'] : null,
                isset($ligne['taux_tva_livraison_id']) ? (int) $ligne['taux_tva_livraison_id'] : null,
            ]);

            $menuStmt = $db->prepare('SELECT quantite_restante FROM menu WHERE menu_id = ?');
            $menuStmt->execute([(int) $ligne['menu_id']]);
            if ($menuStmt->fetchColumn() !== null) {
                $db->prepare('UPDATE menu SET quantite_restante = quantite_restante - 1 WHERE menu_id = ?')
                    ->execute([(int) $ligne['menu_id']]);
            }
        }

        CommandeModel::addHistorique(
            $commandeId,
            null,
            OrderStatus::initial(),
            'Commande payée et créée par webhook Stripe',
            null,
        );

        return $commandeId;
    }

    private static function markEventProcessed(PDO $db, string $eventId): void
    {
        $stmt = $db->prepare(
            "UPDATE stripe_webhook_event
             SET status = 'processed', processed_at = NOW(), last_error = NULL
             WHERE event_id = ?",
        );
        $stmt->execute([$eventId]);
    }

    private static function recordEventFailure(
        string $eventId,
        string $eventType,
        string $objectId,
        string $message,
    ): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO stripe_webhook_event (event_id, event_type, object_id, status, last_error)
                 VALUES (?, ?, ?, 'failed', ?)
                 ON DUPLICATE KEY UPDATE
                    status = 'failed',
                    last_error = VALUES(last_error),
                    object_id = VALUES(object_id),
                    updated_at = CURRENT_TIMESTAMP",
            );
            $stmt->execute([$eventId, $eventType, $objectId, mb_substr($message, 0, 4000)]);
        } catch (Throwable $trackingError) {
            error_log('[stripe-webhook] impossible de tracer l’échec event=' . $eventId . ': ' . $trackingError->getMessage());
        }
    }
}
