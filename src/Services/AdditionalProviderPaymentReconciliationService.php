<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Payments\PaymentProviderEvent;
use App\Payments\PaymentReconciliationContract;
use RuntimeException;
use Throwable;

final class AdditionalProviderPaymentReconciliationService
{
    public static function recordIfNeeded(PaymentProviderEvent $event, int $commandeId): bool
    {
        $session = $event->checkout;
        if ($event->kind !== PaymentProviderEvent::CHECKOUT_PAID || $session === null || $commandeId <= 0) {
            return false;
        }

        $draftId = (int) ($session->metadata['draft_id'] ?? 0);
        $attemptId = (int) ($session->metadata['attempt_id'] ?? 0);
        if ($draftId <= 0 || $attemptId <= 0) {
            return false;
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $draftStmt = $db->prepare('SELECT * FROM order_draft WHERE draft_id = ? FOR UPDATE');
            $draftStmt->execute([$draftId]);
            $draft = $draftStmt->fetch();
            if (!$draft || (int) ($draft['commande_id'] ?? 0) !== $commandeId || (string) ($draft['status'] ?? '') !== 'consumed') {
                throw new RuntimeException('Draft consommé incohérent pour la réconciliation financière.');
            }

            $attemptStmt = $db->prepare(
                'SELECT * FROM payment_attempt WHERE attempt_id = ? AND draft_id = ? FOR UPDATE',
            );
            $attemptStmt->execute([$attemptId, $draftId]);
            $attempt = $attemptStmt->fetch();
            if (!$attempt) {
                throw new RuntimeException('Tentative additionnelle introuvable.');
            }

            $validated = PaymentReconciliationContract::assertPaidCheckout($session, $draft, $attempt);
            if ((string) ($attempt['status'] ?? '') === 'paid') {
                $db->commit();
                return false;
            }

            $update = $db->prepare(
                "UPDATE payment_attempt
                 SET status = 'paid',
                     provider_payment_intent_id = COALESCE(?, provider_payment_intent_id),
                     last_error = 'Encaissement fournisseur additionnel confirmé ; réconciliation financière requise.'
                 WHERE attempt_id = ? AND draft_id = ? AND provider = ? AND status <> 'paid'",
            );
            $update->execute([
                $validated['payment_intent'],
                $attemptId,
                $draftId,
                $event->provider,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Tentative additionnelle non mise à jour.');
            }

            $db->commit();
            error_log(sprintf(
                '[payment-reconciliation] encaissement additionnel commande=%d draft=%d attempt=%d provider=%s',
                $commandeId,
                $draftId,
                $attemptId,
                $event->provider,
            ));

            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
