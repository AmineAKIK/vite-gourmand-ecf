<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationModel;
use App\Models\PaymentAttemptModel;
use App\Models\UserModel;
use App\Payments\PaymentGatewayFactory;
use App\Payments\PaymentSuccessReconciliation;
use App\Services\MailService;
use App\Services\PaymentWebhookFulfillmentService;
use Throwable;

final class StripeFulfillmentController
{
    public function webhook(): void
    {
        $payload = file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        try {
            $event = PaymentGatewayFactory::webhookForProvider('stripe')->parse(
                is_string($payload) ? $payload : '',
                $signature,
            );
        } catch (Throwable $e) {
            error_log('[payment-webhook] signature/provider event invalide: ' . $e->getMessage());
            http_response_code(400);
            exit;
        }

        try {
            $result = PaymentWebhookFulfillmentService::handle($event);
        } catch (Throwable $e) {
            error_log('[payment-webhook] reconciliation échouée provider=' . $event->provider . ' event=' . $event->id . ': ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['received' => false]);
            exit;
        }

        if ($result['processed'] && !$result['duplicate'] && $result['commande_id'] !== null) {
            $this->afterFulfillment($result['commande_id'], $result['commande_data'], $result['panier']);
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
            $context = PaymentAttemptModel::findProviderContextForUser('stripe', $sessionId, (int) $user['id']);
        } catch (Throwable $e) {
            error_log('[payment] résolution success impossible: ' . $e->getMessage());
            flash('error', 'Impossible de vérifier votre paiement. Contactez-nous.');
            redirect('/mon-compte');
        }

        if (!$context) {
            error_log('[payment] success non rattaché user_id=' . (int) $user['id']);
            flash('error', 'Ce paiement ne correspond pas à votre compte.');
            redirect('/mon-compte');
        }

        $state = PaymentSuccessReconciliation::state($context['draft'], $context['attempt']);
        if ($state === PaymentSuccessReconciliation::CONFIRMED) {
            $currentCart = is_array($_SESSION['panier'] ?? null) ? $_SESSION['panier'] : [];
            $draftCart = is_array($context['draft']['panier'] ?? null) ? $context['draft']['panier'] : [];
            if (PaymentSuccessReconciliation::shouldClearCart($currentCart, $draftCart)) {
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

        if ($state === PaymentSuccessReconciliation::PENDING) {
            flash('success', 'Paiement en cours de confirmation automatique.');
            redirect('/mon-compte');
        }

        if ($state === PaymentSuccessReconciliation::FAILED) {
            flash('error', 'Le paiement n’a pas été confirmé. Vous pouvez réessayer.');
            redirect('/panier');
        }

        error_log(sprintf(
            '[payment] état success inattendu session=%s draft=%d attempt=%d state=%s',
            $sessionId,
            (int) $context['draft']['draft_id'],
            (int) $context['attempt']['attempt_id'],
            $state,
        ));
        flash('error', 'L’état du paiement doit être vérifié. Contactez-nous.');
        redirect('/mon-compte');
    }

    private function clearMatchingBrowserPaymentState(string $sessionId, int $draftId, int $attemptId): void
    {
        $storedSessionId = (string) (
            $_SESSION['payment_provider_session_id']
            ?? $_SESSION['stripe_session_id']
            ?? ''
        );
        $storedDraftId = (int) ($_SESSION['payment_draft_id'] ?? $_SESSION['stripe_draft_id'] ?? 0);
        $storedAttemptId = (int) ($_SESSION['payment_attempt_id'] ?? $_SESSION['stripe_attempt_id'] ?? 0);

        if ($storedSessionId === $sessionId || ($storedDraftId === $draftId && $storedAttemptId === $attemptId)) {
            unset(
                $_SESSION['payment_provider'],
                $_SESSION['payment_draft_id'],
                $_SESSION['payment_attempt_id'],
                $_SESSION['payment_provider_session_id'],
                $_SESSION['stripe_pending'],
                $_SESSION['stripe_draft_id'],
                $_SESSION['stripe_attempt_id'],
                $_SESSION['stripe_session_id'],
            );
        }
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
            error_log('[payment-webhook] notification commande_id=' . $commandeId . ' impossible: ' . $e->getMessage());
        }

        try {
            $user = $userId > 0 ? UserModel::findById($userId) : null;
            if ($user && !empty($user['email'])) {
                MailService::sendCommandeConfirmation((string) $user['email'], $commandeData, $panier);
            }
        } catch (Throwable $e) {
            error_log('[payment-webhook] email confirmation commande_id=' . $commandeId . ' impossible: ' . $e->getMessage());
        }
    }
}
