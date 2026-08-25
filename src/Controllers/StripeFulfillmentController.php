<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\OperatorConfiguration;
use App\Domain\StripeSuccessReconciliation;
use App\Domain\StripeWebhookContract;
use App\Models\NotificationModel;
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
        $stripeSecretKey = OperatorConfiguration::string('operator.stripe.secret_key');
        $webhookSecret = OperatorConfiguration::string('operator.stripe.webhook_secret');

        if ($stripeSecretKey === '' || $webhookSecret === '') {
            http_response_code(400);
            exit;
        }

        \Stripe\Stripe::setApiKey($stripeSecretKey);
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (Throwable $e) {
            error_log('[stripe-webhook] signature invalide: ' . $e->getMessage());
            http_response_code(400);
            exit;
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $sessionData = $this->sessionData($session);
            $metadata = $sessionData['metadata'];

            if ((int) ($metadata['draft_id'] ?? 0) <= 0 || (int) ($metadata['attempt_id'] ?? 0) <= 0) {
                error_log('[stripe-webhook] session sans contrat V1 ignorée event=' . (string) $event->id);
            } else {
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

        $sessionId = trim((string) ($_GET['session_id'] ?? ''));
        $user = currentUser();
        if ($sessionId === '' || strlen($sessionId) > 255 || !$user) {
            flash('error', 'Référence de paiement invalide.');
            redirect('/mon-compte');
        }

        try {
            $context = PaymentAttemptModel::findStripeContextForUser($sessionId, (int) $user['id']);
        } catch (Throwable $e) {
            error_log('[payment] résolution success impossible: ' . $e->getMessage());
            flash('error', 'Impossible de vérifier votre paiement. Contactez-nous.');
            redirect('/mon-compte');
        }

        if (!$context) {
            error_log('[payment] success Stripe non rattaché user_id=' . (int) $user['id']);
            flash('error', 'Ce paiement ne correspond pas à votre compte.');
            redirect('/mon-compte');
        }

        $stripeSecretKey = OperatorConfiguration::string('operator.stripe.secret_key');
        if ($stripeSecretKey === '') {
            flash('error', 'Impossible de vérifier votre paiement. Contactez-nous.');
            redirect('/mon-compte');
        }

        \Stripe\Stripe::setApiKey($stripeSecretKey);
        try {
            $stripeSession = \Stripe\Checkout\Session::retrieve($sessionId);
        } catch (Throwable $e) {
            error_log('[payment] lecture Stripe success impossible: ' . $e->getMessage());
            flash('error', 'Impossible de vérifier votre paiement. Contactez-nous.');
            redirect('/mon-compte');
        }

        $sessionData = $this->sessionData($stripeSession);
        if ((string) $sessionData['payment_status'] !== 'paid') {
            flash('error', 'Le paiement n’a pas été complété.');
            redirect('/panier');
        }

        try {
            StripeWebhookContract::assertPaidSession(
                $sessionData,
                $context['draft'],
                $context['attempt'],
            );
        } catch (Throwable $e) {
            error_log('[payment] réconciliation Stripe incohérente session=' . $sessionId . ': ' . $e->getMessage());
            flash('error', 'Le paiement reçu ne correspond pas à la commande préparée. Contactez-nous.');
            redirect('/mon-compte');
        }

        try {
            $freshContext = PaymentAttemptModel::findStripeContextForUser($sessionId, (int) $user['id']);
            if ($freshContext) {
                $context = $freshContext;
            }
        } catch (Throwable $e) {
            error_log('[payment] relecture état fulfillment impossible session=' . $sessionId . ': ' . $e->getMessage());
        }

        $state = StripeSuccessReconciliation::state($context['draft'], $context['attempt']);
        if ($state === StripeSuccessReconciliation::CONFIRMED) {
            $currentCart = is_array($_SESSION['panier'] ?? null) ? $_SESSION['panier'] : [];
            $draftCart = is_array($context['draft']['panier'] ?? null) ? $context['draft']['panier'] : [];
            if (StripeSuccessReconciliation::shouldClearCart($currentCart, $draftCart)) {
                $_SESSION['panier'] = [];
            }

            $this->clearMatchingBrowserPaymentState(
                $sessionId,
                (int) $context['draft']['draft_id'],
                (int) $context['attempt']['attempt_id'],
            );
            flash('success', 'Paiement confirmé ! Votre commande a bien été enregistrée.');
            redirect('/mon-compte');
        }

        if ($state === StripeSuccessReconciliation::PENDING) {
            flash('success', 'Paiement reçu. Votre commande est en cours de confirmation automatique.');
            redirect('/mon-compte');
        }

        error_log(sprintf(
            '[payment] état success inattendu session=%s draft=%d attempt=%d state=%s',
            $sessionId,
            (int) $context['draft']['draft_id'],
            (int) $context['attempt']['attempt_id'],
            $state,
        ));
        flash('error', 'Votre paiement a été reçu mais son enregistrement doit être vérifié. Contactez-nous.');
        redirect('/mon-compte');
    }

    private function clearMatchingBrowserPaymentState(string $sessionId, int $draftId, int $attemptId): void
    {
        $storedSessionId = (string) ($_SESSION['stripe_session_id'] ?? '');
        $storedDraftId = (int) ($_SESSION['stripe_draft_id'] ?? 0);
        $storedAttemptId = (int) ($_SESSION['stripe_attempt_id'] ?? 0);

        if ($storedSessionId === $sessionId || ($storedDraftId === $draftId && $storedAttemptId === $attemptId)) {
            unset(
                $_SESSION['stripe_pending'],
                $_SESSION['stripe_draft_id'],
                $_SESSION['stripe_attempt_id'],
                $_SESSION['stripe_session_id'],
            );
        }
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
