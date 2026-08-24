<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationModel;
use App\Models\PaiementModel;
use App\Models\PaymentAttemptModel;
use App\Models\UserModel;
use App\Services\MailService;
use App\Services\StripeWebhookFulfillmentService;
use Throwable;

final class StripeFulfillmentController
{
    public function webhook(): void
    {
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (!STRIPE_WEBHOOK_SECRET || str_starts_with(STRIPE_WEBHOOK_SECRET, 'whsec_REMPLACER')) {
            http_response_code(400);
            exit;
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
        } catch (Throwable $e) {
            error_log('[stripe-webhook] signature invalide: ' . $e->getMessage());
            http_response_code(400);
            exit;
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $sessionData = $this->sessionData($session);
            $metadata = $sessionData['metadata'];

            if ((int) ($metadata['draft_id'] ?? 0) > 0 && (int) ($metadata['attempt_id'] ?? 0) > 0) {
                try {
                    $result = StripeWebhookFulfillmentService::fulfillCheckoutSessionCompleted(
                        (string) $event->id,
                        $sessionData,
                    );
                } catch (Throwable $e) {
                    error_log('[stripe-webhook] fulfillment échoué event=' . (string) $event->id . ': ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['received' => false]);
                    exit;
                }

                if ($result['processed'] && !$result['duplicate'] && $result['commande_id'] !== null) {
                    $this->afterFulfillment($result['commande_id'], $result['commande_data'], $result['panier']);
                }
            } else {
                $this->processLegacyCompletedSession($session);
            }
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['received' => true]);
        exit;
    }

    public function success(): void
    {
        requireAuth();

        if (!isset($_SESSION['stripe_draft_id'])) {
            (new StripeController())->success();
            return;
        }

        $sessionId = sanitize($_GET['session_id'] ?? '');
        $user = currentUser();
        $draftId = (int) ($_SESSION['stripe_draft_id'] ?? 0);
        $attemptId = (int) ($_SESSION['stripe_attempt_id'] ?? 0);

        if (!$sessionId || !$user || $draftId <= 0 || $attemptId <= 0) {
            flash('error', 'Paiement non confirmé.');
            redirect('/mon-compte');
        }

        try {
            $draft = PaymentAttemptModel::findDraftForUser($draftId, (int) $user['id']);
            $attempt = PaymentAttemptModel::findAttemptForDraft($attemptId, $draftId);
        } catch (Throwable $e) {
            error_log('[payment] lecture réconciliation success impossible: ' . $e->getMessage());
            flash('error', 'Impossible de vérifier votre paiement. Contactez-nous.');
            redirect('/mon-compte');
        }

        if (!$draft || !$attempt || (string) ($attempt['provider_session_id'] ?? '') !== $sessionId) {
            flash('error', 'Référence de paiement invalide.');
            redirect('/mon-compte');
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        try {
            $stripeSession = \Stripe\Checkout\Session::retrieve($sessionId);
        } catch (Throwable $e) {
            error_log('[payment] lecture Stripe success impossible: ' . $e->getMessage());
            flash('error', 'Impossible de vérifier votre paiement. Contactez-nous.');
            redirect('/mon-compte');
        }

        if ((string) $stripeSession->payment_status !== 'paid') {
            flash('error', 'Le paiement n’a pas été complété.');
            redirect('/panier');
        }

        try {
            $draft = PaymentAttemptModel::findDraftForUser($draftId, (int) $user['id']) ?? $draft;
        } catch (Throwable $e) {
            error_log('[payment] relecture draft après paiement impossible: ' . $e->getMessage());
        }

        unset(
            $_SESSION['stripe_pending'],
            $_SESSION['stripe_draft_id'],
            $_SESSION['stripe_attempt_id'],
            $_SESSION['stripe_session_id'],
        );
        $_SESSION['panier'] = [];

        if (!empty($draft['commande_id'])) {
            flash('success', 'Paiement confirmé ! Votre commande a bien été enregistrée.');
        } else {
            flash('success', 'Paiement reçu. Votre commande est en cours de confirmation automatique.');
        }

        redirect('/mon-compte');
    }

    private function sessionData(object $session): array
    {
        $metadata = [];
        foreach (['draft_id', 'attempt_id', 'numero_commande', 'utilisateur_id', 'expected_total_cents', 'currency'] as $key) {
            if (isset($session->metadata->{$key})) {
                $metadata[$key] = (string) $session->metadata->{$key};
            }
        }

        return [
            'id' => (string) ($session->id ?? ''),
            'payment_status' => (string) ($session->payment_status ?? ''),
            'amount_total' => (int) ($session->amount_total ?? 0),
            'currency' => strtolower((string) ($session->currency ?? '')),
            'client_reference_id' => (string) ($session->client_reference_id ?? ''),
            'payment_intent' => isset($session->payment_intent) ? (string) $session->payment_intent : null,
            'metadata' => $metadata,
        ];
    }

    private function processLegacyCompletedSession(object $session): void
    {
        $ref = (string) ($session->client_reference_id ?? '');
        if ($ref === '') {
            return;
        }

        $commande = db()->fetchOne(
            'SELECT commande_id, prix_total FROM commande WHERE numero_commande = ?',
            [$ref],
        );
        if (!$commande) {
            return;
        }

        $already = db()->fetchOne(
            "SELECT paiement_id FROM paiement WHERE commande_id = ? AND mode = 'cb_online'",
            [$commande['commande_id']],
        );
        if ($already) {
            return;
        }

        PaiementModel::create([
            'commande_id' => $commande['commande_id'],
            'type_paiement' => 'paiement_unique',
            'montant' => $commande['prix_total'],
            'mode' => 'cb_online',
            'date_paiement' => date('Y-m-d'),
            'reference' => $session->payment_intent ?? $session->id,
            'note' => 'Paiement Stripe legacy via webhook — session ' . $session->id,
        ], null);
    }

    private function afterFulfillment(int $commandeId, ?array $commandeData, ?array $panier): void
    {
        if (!$commandeData || !$panier) {
            return;
        }

        $userId = (int) ($commandeData['utilisateur_id'] ?? 0);
        $numero = (string) ($commandeData['numero_commande'] ?? $commandeId);

        try {
            $user = $userId > 0 ? UserModel::findById($userId) : null;
            $clientNom = $user ? trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) : 'Client';
            NotificationModel::notifyEmployesNouvelleCommande($commandeId, $numero, $clientNom ?: 'Client');
        } catch (Throwable $e) {
            error_log('[stripe-webhook] notification commande_id=' . $commandeId . ' impossible: ' . $e->getMessage());
        }

        try {
            $user = $userId > 0 ? UserModel::findById($userId) : null;
            if ($user && !empty($user['email'])) {
                MailService::sendCommandeConfirmation((string) $user['email'], $commandeData, $panier);
            }
        } catch (Throwable $e) {
            error_log('[stripe-webhook] email confirmation commande_id=' . $commandeId . ' impossible: ' . $e->getMessage());
        }
    }
}
