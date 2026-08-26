<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\ConfigurationIncompleteException;
use App\Config\OperatorConfiguration;
use App\Models\MenuModel;
use App\Models\PaymentAttemptModel;
use App\Payments\PaymentCheckoutContract;
use App\Payments\PaymentCheckoutRequest;
use App\Payments\PaymentCheckoutSession;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayFactory;
use App\Services\PaymentMethodRegistry;
use RuntimeException;
use Throwable;

final class StripeController
{
    public function checkout(): void
    {
        requireAuth();

        $pending = $this->loadPendingPayment();
        if ($pending === null) {
            flash('error', 'Session expirée. Veuillez recommencer votre commande.');
            redirect('/panier');
        }

        $provider = (string) $pending['attempt']['provider'];
        $paymentMethodCode = (string) ($pending['commande_data']['payment_method_code'] ?? '');
        try {
            $paymentMethod = PaymentMethodRegistry::requireCheckoutMethod($paymentMethodCode);
            if (($paymentMethod['provider'] ?? null) !== $provider) {
                throw new RuntimeException('Le fournisseur de paiement ne correspond plus à la politique active.');
            }
            $gateway = PaymentGatewayFactory::forProvider($provider);
            $pending = $this->preparePersistedAttempt($pending, $gateway);
            $expiresAt = PaymentCheckoutContract::sessionExpiresAt((string) $pending['draft']['expires_at']);
        } catch (ConfigurationIncompleteException|\InvalidArgumentException|RuntimeException $e) {
            error_log('[payment] checkout fournisseur indisponible: ' . $e->getMessage());
            flash('error', 'Le paiement en ligne n’est plus disponible. Choisissez un autre moyen de paiement.');
            redirect('/panier');
        } catch (Throwable $e) {
            $this->trackProviderError($pending, $e);
            flash('error', 'Impossible de vérifier la tentative de paiement. Veuillez réessayer.');
            redirect('/panier');
        }

        if ($this->redirectToExistingSession($pending)) {
            return;
        }

        try {
            $request = $this->checkoutRequest($pending, $provider, $expiresAt);
            $session = $gateway->createCheckout($request);
            PaymentAttemptModel::bindProviderSession(
                (int) $pending['attempt']['attempt_id'],
                $provider,
                $session->id,
            );
        } catch (Throwable $e) {
            $this->trackProviderError($pending, $e);
            flash('error', 'Impossible de préparer le paiement en ligne. Veuillez réessayer.');
            redirect('/panier');
        }

        if ($session->url === null || $session->url === '') {
            PaymentAttemptModel::recordAttemptError(
                (int) $pending['attempt']['attempt_id'],
                'Session fournisseur créée sans URL de redirection.',
            );
            flash('error', 'Impossible de finaliser la préparation du paiement. Veuillez réessayer.');
            redirect('/panier');
        }

        $_SESSION['payment_provider_session_id'] = $session->id;
        header('Location: ' . $session->url);
        exit;
    }

    public function cancel(): void
    {
        $draftId = (int) ($_SESSION['payment_draft_id'] ?? 0);
        $attemptId = (int) ($_SESSION['payment_attempt_id'] ?? 0);
        if ($draftId > 0) {
            try {
                PaymentAttemptModel::markDraftStatus($draftId, 'cancelled');
                if ($attemptId > 0) {
                    PaymentAttemptModel::markAttemptStatus($attemptId, 'cancelled');
                }
            } catch (Throwable $e) {
                error_log('[payment] annulation tracking draft impossible: ' . $e->getMessage());
            }
        }

        $this->clearBrowserPaymentState();
        flash('error', 'Paiement annulé. Votre commande n\'a pas été enregistrée.');
        redirect('/panier');
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    private function preparePersistedAttempt(array $pending, PaymentGateway $gateway): array
    {
        $user = currentUser();
        PaymentCheckoutContract::assertCompatible($pending['draft'], $pending['attempt'], (int) $user['id']);

        $attempt = $pending['attempt'];
        if ((string) $attempt['provider'] !== $gateway->provider()) {
            throw new RuntimeException('Fournisseur de tentative incohérent.');
        }
        if (in_array((string) $attempt['status'], ['failed', 'cancelled'], true)) {
            return $this->replaceAttempt($pending, $gateway->provider());
        }

        if (!empty($attempt['provider_session_id'])) {
            try {
                $session = $gateway->retrieveCheckout((string) $attempt['provider_session_id']);
            } catch (Throwable $e) {
                throw new RuntimeException('Session de paiement temporairement indisponible.', 0, $e);
            }

            if ($session->isExpired()) {
                PaymentAttemptModel::failAttempt((int) $attempt['attempt_id'], 'Session fournisseur expirée.');
                return $this->replaceAttempt($pending, $gateway->provider());
            }

            $pending['provider_session'] = $session;
        }

        return $pending;
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    private function replaceAttempt(array $pending, string $provider): array
    {
        $attemptId = PaymentAttemptModel::createRetryAttempt((int) $pending['draft']['draft_id'], $provider);
        $attempt = PaymentAttemptModel::findAttemptForDraft($attemptId, (int) $pending['draft']['draft_id']);
        if ($attempt === null) {
            throw new RuntimeException('Nouvelle tentative de paiement introuvable.');
        }

        PaymentCheckoutContract::assertCompatible(
            $pending['draft'],
            $attempt,
            (int) currentUser()['id'],
        );

        $_SESSION['payment_attempt_id'] = $attemptId;
        unset($_SESSION['payment_provider_session_id']);
        $pending['attempt'] = $attempt;
        unset($pending['provider_session']);

        return $pending;
    }

    /** @param array<string,mixed> $pending */
    private function redirectToExistingSession(array $pending): bool
    {
        if (!isset($pending['provider_session']) || !$pending['provider_session'] instanceof PaymentCheckoutSession) {
            return false;
        }

        $session = $pending['provider_session'];
        if ($session->isOpen() && $session->url !== null && $session->url !== '') {
            $_SESSION['payment_provider_session_id'] = $session->id;
            header('Location: ' . $session->url);
            exit;
        }

        if ($session->isComplete()) {
            redirect('/stripe/success?session_id=' . rawurlencode($session->id));
        }

        return false;
    }

    /** @param array<string,mixed> $pending */
    private function checkoutRequest(array $pending, string $provider, int $expiresAt): PaymentCheckoutRequest
    {
        $commandeData = $pending['commande_data'];
        $pricing = $pending['pricing'];
        $draft = $pending['draft'];
        $attempt = $pending['attempt'];

        $expectedCents = (int) ($pricing['total_ttc_cents'] ?? 0);
        $grossCents = (int) ($pricing['total_brut_cents'] ?? 0);
        $deliveryCents = (int) ($pricing['prix_livraison_cents'] ?? 0);
        $discountCents = (int) ($pricing['remise_globale_cents'] ?? 0);
        $currency = strtolower((string) ($pricing['currency'] ?? ''));

        if ($grossCents + $deliveryCents - $discountCents !== $expectedCents) {
            throw new RuntimeException('Le montant de la commande est incohérent.');
        }
        if ((int) $draft['expected_total_cents'] !== $expectedCents
            || strtolower((string) $draft['currency']) !== $currency) {
            throw new RuntimeException('Le snapshot de paiement ne correspond plus au panier.');
        }

        $items = [];
        foreach ((array) ($pricing['lignes'] ?? []) as $ligne) {
            $personCount = (int) ($ligne['nombre_personne'] ?? 0);
            $lineGrossCents = (int) ($ligne['prix_menu_brut_cents'] ?? 0);
            if ($lineGrossCents <= 0) {
                $lineGrossCents = (int) ($ligne['prix_par_personne_snapshot_cents'] ?? 0) * $personCount;
            }
            $menu = MenuModel::getById((int) ($ligne['menu_id'] ?? 0));
            $items[] = [
                'name' => (string) (($menu['titre'] ?? 'Menu') . ' × ' . $personCount . ' pers.'),
                'amount_cents' => $lineGrossCents,
            ];
        }
        if ($deliveryCents > 0) {
            $items[] = ['name' => 'Frais de livraison', 'amount_cents' => $deliveryCents];
        }

        $baseUrl = OperatorConfiguration::string('operator.base_url');

        return new PaymentCheckoutRequest(
            attemptId: (int) $attempt['attempt_id'],
            draftId: (int) $draft['draft_id'],
            orderReference: (string) $commandeData['numero_commande'],
            userId: (int) $commandeData['utilisateur_id'],
            expectedAmountCents: $expectedCents,
            currency: $currency,
            expiresAt: $expiresAt,
            successUrl: PaymentGatewayFactory::successUrl($provider, $baseUrl),
            cancelUrl: PaymentGatewayFactory::cancelUrl($provider, $baseUrl),
            items: $items,
            discountCents: $discountCents,
        );
    }

    /** @param array<string,mixed> $pending */
    private function trackProviderError(array $pending, Throwable $e): void
    {
        if (isset($pending['attempt']['attempt_id'])) {
            try {
                PaymentAttemptModel::recordAttemptError((int) $pending['attempt']['attempt_id'], $e->getMessage());
            } catch (Throwable $trackingError) {
                error_log('[payment] tracking erreur fournisseur impossible: ' . $trackingError->getMessage());
            }
        }

        error_log('[payment] appel fournisseur ambigu: ' . get_class($e) . ': ' . $e->getMessage());
    }

    /** @return array<string,mixed>|null */
    private function loadPendingPayment(): ?array
    {
        $user = currentUser();
        $draftId = (int) ($_SESSION['payment_draft_id'] ?? 0);
        $provider = strtolower(trim((string) ($_SESSION['payment_provider'] ?? '')));

        if ($draftId <= 0 || $provider === '' || !$user || !PaymentGatewayFactory::supports($provider)) {
            return null;
        }

        try {
            $draft = PaymentAttemptModel::findDraftForUser($draftId, (int) $user['id']);
            if (!$draft || $draft['status'] !== 'pending_payment') {
                return null;
            }
            if (!empty($draft['expires_at']) && strtotime((string) $draft['expires_at']) < time()) {
                PaymentAttemptModel::markDraftStatus($draftId, 'failed');
                return null;
            }

            $attemptId = (int) ($_SESSION['payment_attempt_id'] ?? 0);
            $attempt = $attemptId > 0
                ? PaymentAttemptModel::findAttemptForDraft($attemptId, $draftId)
                : PaymentAttemptModel::latestAttemptForDraft($draftId);
            if (!$attempt || (string) $attempt['provider'] !== $provider) {
                return null;
            }

            return [
                'commande_data' => $draft['commande_data'],
                'pricing' => $draft['pricing'],
                'panier' => $draft['panier'],
                'draft' => $draft,
                'attempt' => $attempt,
            ];
        } catch (Throwable $e) {
            error_log('[payment] lecture draft impossible draft_id=' . $draftId . ': ' . $e->getMessage());
            return null;
        }
    }

    private function clearBrowserPaymentState(): void
    {
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
