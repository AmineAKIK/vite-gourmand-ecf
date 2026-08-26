<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\OrderStatus;
use App\Models\PaiementModel;
use App\Payments\PaymentProviderEvent;
use App\Payments\PaymentReconciliationContract;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class PaymentWebhookFulfillmentService
{
    /**
     * @return array{processed:bool,duplicate:bool,commande_id:?int,commande_data:?array,panier:?array}
     */
    public static function handle(PaymentProviderEvent $event): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            PaymentProviderEventInbox::claim($db, $event);
            $inbox = PaymentProviderEventInbox::lock($db, $event->provider, $event->id);
            if (in_array((string) ($inbox['status'] ?? ''), ['processed', 'ignored'], true)) {
                $db->commit();
                return self::result(true, true);
            }

            $result = match ($event->kind) {
                PaymentProviderEvent::CHECKOUT_PAID => self::fulfillPaidCheckout($db, $event),
                PaymentProviderEvent::CHECKOUT_EXPIRED => self::recordCheckoutExpired($db, $event),
                PaymentProviderEvent::PAYMENT_FAILED => self::recordPaymentFailed($db, $event),
                PaymentProviderEvent::IGNORED => self::ignore($db, $event),
                default => throw new RuntimeException('Événement de paiement non supporté.'),
            };

            $db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            PaymentProviderEventInbox::recordFailure($db, $event, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array{processed:bool,duplicate:bool,commande_id:?int,commande_data:?array,panier:?array}
     */
    private static function fulfillPaidCheckout(PDO $db, PaymentProviderEvent $event): array
    {
        $session = $event->checkout;
        if ($session === null) {
            throw new RuntimeException('Session payée absente de l’événement.');
        }

        $draftId = (int) ($session->metadata['draft_id'] ?? 0);
        $attemptId = (int) ($session->metadata['attempt_id'] ?? 0);
        if ($draftId <= 0 || $attemptId <= 0) {
            PaymentProviderEventInbox::markIgnored($db, $event);
            return self::result(false, false);
        }

        [$draft, $attempt] = self::lockDraftAndAttempt($db, $draftId, $attemptId);
        $validated = PaymentReconciliationContract::assertPaidCheckout($session, $draft, $attempt);

        if (!empty($draft['commande_id'])) {
            PaymentProviderEventInbox::markProcessed($db, $event);
            return self::result(true, true, (int) $draft['commande_id']);
        }

        [$commandeData, $pricing, $panier] = self::decodeSnapshots($draft);
        $commandeData['prix_total_cents'] = (int) $validated['amount_total'];
        $paymentMethodCode = trim((string) ($commandeData['payment_method_code'] ?? ''));
        if ($paymentMethodCode === '') {
            throw new RuntimeException('Le draft ne porte aucun moyen de paiement snapshoté.');
        }

        $commandeId = self::createCommande($db, $commandeData, $pricing['lignes'] ?? [], $event->provider);
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
            'mode' => $paymentMethodCode,
            'date_paiement' => date('Y-m-d'),
            'reference' => $validated['payment_intent'] ?? $session->id,
            'note' => sprintf(
                'Paiement fournisseur autoritatif (%s) — session %s',
                $event->provider,
                $session->id,
            ),
        ], null);

        InventoryLedgerService::consumeOrder($db, $commandeId, null);

        $attemptStmt = $db->prepare(
            "UPDATE payment_attempt
             SET status = 'paid', provider_payment_intent_id = COALESCE(?, provider_payment_intent_id), last_error = NULL
             WHERE attempt_id = ? AND draft_id = ? AND provider = ?",
        );
        $attemptStmt->execute([
            $validated['payment_intent'],
            $attemptId,
            $draftId,
            $event->provider,
        ]);
        if ($attemptStmt->rowCount() !== 1) {
            throw new RuntimeException('La tentative de paiement n’a pas pu être confirmée.');
        }

        $draftStmt = $db->prepare(
            "UPDATE order_draft
             SET commande_id = ?, status = 'consumed'
             WHERE draft_id = ? AND commande_id IS NULL",
        );
        $draftStmt->execute([$commandeId, $draftId]);
        if ($draftStmt->rowCount() !== 1) {
            throw new RuntimeException('Le draft a déjà été consommé.');
        }

        PaymentProviderEventInbox::markProcessed($db, $event);
        return self::result(true, false, $commandeId, $commandeData, $panier);
    }

    /**
     * @return array{processed:bool,duplicate:bool,commande_id:?int,commande_data:?array,panier:?array}
     */
    private static function recordCheckoutExpired(PDO $db, PaymentProviderEvent $event): array
    {
        $session = $event->checkout;
        if ($session === null) {
            PaymentProviderEventInbox::markIgnored($db, $event);
            return self::result(false, false);
        }

        $draftId = (int) ($session->metadata['draft_id'] ?? 0);
        $attemptId = (int) ($session->metadata['attempt_id'] ?? 0);
        if ($draftId > 0 && $attemptId > 0) {
            [$draft, $attempt] = self::lockDraftAndAttempt($db, $draftId, $attemptId);
            if ((string) $attempt['provider'] === $event->provider
                && (string) ($attempt['provider_session_id'] ?? '') === $session->id
                && (string) $attempt['status'] !== 'paid'
                && (string) $draft['status'] !== 'consumed') {
                $stmt = $db->prepare(
                    "UPDATE payment_attempt
                     SET status = 'failed', last_error = 'Session fournisseur expirée.'
                     WHERE attempt_id = ? AND draft_id = ? AND status <> 'paid'",
                );
                $stmt->execute([$attemptId, $draftId]);
            }
        }

        PaymentProviderEventInbox::markProcessed($db, $event);
        return self::result(true, false);
    }

    /**
     * @return array{processed:bool,duplicate:bool,commande_id:?int,commande_data:?array,panier:?array}
     */
    private static function recordPaymentFailed(PDO $db, PaymentProviderEvent $event): array
    {
        if ($event->paymentIntentId !== null && $event->paymentIntentId !== '') {
            $stmt = $db->prepare(
                "UPDATE payment_attempt pa
                 JOIN order_draft od ON od.draft_id = pa.draft_id
                 SET pa.status = 'failed', pa.last_error = 'Paiement fournisseur refusé.'
                 WHERE pa.provider = ?
                   AND pa.provider_payment_intent_id = ?
                   AND pa.status <> 'paid'
                   AND od.status <> 'consumed'",
            );
            $stmt->execute([$event->provider, $event->paymentIntentId]);
        }

        PaymentProviderEventInbox::markProcessed($db, $event);
        return self::result(true, false);
    }

    /**
     * @return array{processed:bool,duplicate:bool,commande_id:?int,commande_data:?array,panier:?array}
     */
    private static function ignore(PDO $db, PaymentProviderEvent $event): array
    {
        PaymentProviderEventInbox::markIgnored($db, $event);
        return self::result(false, false);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private static function lockDraftAndAttempt(PDO $db, int $draftId, int $attemptId): array
    {
        $draftStmt = $db->prepare('SELECT * FROM order_draft WHERE draft_id = ? FOR UPDATE');
        $draftStmt->execute([$draftId]);
        $draft = $draftStmt->fetch();
        if (!$draft) {
            throw new RuntimeException('Draft de paiement introuvable.');
        }

        $attemptStmt = $db->prepare(
            'SELECT * FROM payment_attempt WHERE attempt_id = ? AND draft_id = ? FOR UPDATE',
        );
        $attemptStmt->execute([$attemptId, $draftId]);
        $attempt = $attemptStmt->fetch();
        if (!$attempt) {
            throw new RuntimeException('Tentative de paiement introuvable.');
        }

        return [$draft, $attempt];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<mixed>} */
    private static function decodeSnapshots(array $draft): array
    {
        try {
            $commandeData = json_decode((string) $draft['commande_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $pricing = json_decode((string) $draft['pricing_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $panier = json_decode((string) $draft['panier_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Snapshots de paiement illisibles.', 0, $e);
        }

        if (!is_array($commandeData) || !is_array($pricing) || !is_array($panier)) {
            throw new RuntimeException('Snapshots de paiement invalides.');
        }
        if (!isset($pricing['lignes']) || !is_array($pricing['lignes']) || $pricing['lignes'] === []) {
            throw new RuntimeException('Lignes de pricing absentes du draft.');
        }

        return [$commandeData, $pricing, $panier];
    }

    private static function createCommande(PDO $db, array $commandeData, array $lignes, string $provider): int
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

        OrderStatusHistoryService::append(
            $db,
            $commandeId,
            null,
            OrderStatus::initial(),
            'Commande payée et créée par webhook fournisseur (' . $provider . ')',
            null,
        );

        return $commandeId;
    }

    /**
     * @return array{processed:bool,duplicate:bool,commande_id:?int,commande_data:?array,panier:?array}
     */
    private static function result(
        bool $processed,
        bool $duplicate,
        ?int $commandeId = null,
        ?array $commandeData = null,
        ?array $panier = null,
    ): array {
        return [
            'processed' => $processed,
            'duplicate' => $duplicate,
            'commande_id' => $commandeId,
            'commande_data' => $commandeData,
            'panier' => $panier,
        ];
    }
}
