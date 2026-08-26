<?php

namespace App\Controllers;

use App\Config\ConfigurationIncompleteException;
use App\Config\OperatorConfiguration;
use App\Domain\StripeCheckoutContract;
use App\Models\MenuModel;
use App\Models\PaymentAttemptModel;
use App\Services\PaymentMethodRegistry;

class StripeController
{
    public function checkout(): void
    {
        requireAuth();

        try {
            PaymentMethodRegistry::requireCheckoutMethod('cb_online');
        } catch (ConfigurationIncompleteException|\InvalidArgumentException $e) {
            error_log('[payment] checkout Stripe indisponible: ' . $e->getMessage());
            flash('error', 'Le paiement en ligne n’est plus disponible. Choisissez un autre moyen de paiement.');
            redirect('/panier');
        }

        $pending = $this->loadPendingPayment();
        if (!$pending) {
            flash('error', 'Session expirée. Veuillez recommencer votre commande.');
            redirect('/panier');
        }

        $stripeSecretKey = OperatorConfiguration::string('operator.stripe.secret_key');
        if ($stripeSecretKey === '' || str_starts_with($stripeSecretKey, 'sk_test_REMPLACER')) {
            flash('error', 'Le paiement en ligne n\'est pas encore configuré. Choisissez un autre mode de paiement.');
            redirect('/panier');
        }

        \Stripe\Stripe::setApiKey($stripeSecretKey);
        $stripeExpiresAt = null;

        if (isset($pending['draft'], $pending['attempt'])) {
            try {
                $pending = $this->preparePersistedAttempt($pending);
                $stripeExpiresAt = StripeCheckoutContract::sessionExpiresAt((string) $pending['draft']['expires_at']);
            } catch (\Throwable $e) {
                error_log('[payment] contrat Checkout Stripe invalide: ' . $e->getMessage());
                flash('error', 'Le paiement préparé n’est plus valide. Veuillez recommencer votre commande.');
                redirect('/panier');
            }

            if ($this->redirectToExistingSession($pending)) {
                return;
            }
        }

        $commandeData = $pending['commande_data'];
        $pricing = $pending['pricing'];
        $currency = strtolower((string) ($pricing['currency'] ?? 'eur'));

        $expectedCents = (int) ($pricing['total_ttc_cents'] ?? round($pricing['total_ttc'] * 100));
        $grossCents = (int) ($pricing['total_brut_cents'] ?? round($pricing['total_brut'] * 100));
        $deliveryCents = (int) ($pricing['prix_livraison_cents'] ?? round($pricing['prix_livraison_cents'] * 100));
        $discountCents = (int) ($pricing['remise_globale_cents'] ?? round($pricing['remise_globale'] * 100));

        if ($grossCents + $deliveryCents - $discountCents !== $expectedCents) {
            error_log(sprintf(
                '[pricing] invariant Stripe invalide ref=%s brut=%d livraison=%d remise=%d total=%d',
                $commandeData['numero_commande'] ?? 'inconnue',
                $grossCents,
                $deliveryCents,
                $discountCents,
                $expectedCents,
            ));
            flash('error', 'Le montant de la commande est incohérent. Veuillez recommencer votre commande.');
            redirect('/panier');
        }

        if (isset($pending['draft'])) {
            if ((int) $pending['draft']['expected_total_cents'] !== $expectedCents
                || strtolower((string) $pending['draft']['currency']) !== $currency) {
                error_log('[payment] snapshot pricing incohérent draft_id=' . (int) $pending['draft']['draft_id']);
                flash('error', 'Le montant préparé pour Stripe est incohérent. Veuillez recommencer votre commande.');
                redirect('/panier');
            }
        }

        $lineItems = [];
        foreach ($pricing['lignes'] as $ligne) {
            $menu = MenuModel::getById((int) $ligne['menu_id']);
            $lineGrossCents = (int) ($ligne['prix_menu_brut_cents'] ?? round(
                ((float) $ligne['prix_par_personne_snapshot_cents']) * ((int) $ligne['nombre_personne']) * 100,
            ));

            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $lineGrossCents,
                    'product_data' => [
                        'name' => ($menu['titre'] ?? 'Menu') . ' × ' . $ligne['nombre_personne'] . ' pers.',
                    ],
                ],
                'quantity' => 1,
            ];
        }

        if ($deliveryCents > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $deliveryCents,
                    'product_data' => ['name' => 'Frais de livraison'],
                ],
                'quantity' => 1,
            ];
        }

        $discounts = [];
        if ($discountCents > 0) {
            $couponOptions = [];
            if (isset($pending['attempt'])) {
                $couponOptions['idempotency_key'] = StripeCheckoutContract::idempotencyKey(
                    (int) $pending['attempt']['attempt_id'],
                    'coupon',
                );
            }

            try {
                $coupon = \Stripe\Coupon::create([
                    'amount_off' => $discountCents,
                    'currency' => $currency,
                    'duration' => 'once',
                    'name' => 'Réduction fidélité',
                ], $couponOptions);
            } catch (\Throwable $e) {
                $this->trackAmbiguousStripeError($pending, $e);
                flash('error', 'Impossible de préparer la réduction Stripe. Veuillez réessayer.');
                redirect('/panier');
            }
            $discounts = [['coupon' => $coupon->id]];
        }

        $baseUrl = rtrim(OperatorConfiguration::string('operator.base_url'), '/');
        $metadata = [
            'numero_commande' => $commandeData['numero_commande'],
            'utilisateur_id' => (string) $commandeData['utilisateur_id'],
            'expected_total_cents' => (string) $expectedCents,
            'currency' => $currency,
        ];

        $sessionParams = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'discounts' => $discounts,
            'mode' => 'payment',
            'success_url' => $baseUrl . '/stripe/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/stripe/cancel',
            'metadata' => $metadata,
            'client_reference_id' => $commandeData['numero_commande'],
        ];
        $sessionOptions = [];

        if (isset($pending['draft'], $pending['attempt'])) {
            $metadata['draft_id'] = (string) $pending['draft']['draft_id'];
            $metadata['attempt_id'] = (string) $pending['attempt']['attempt_id'];
            $sessionParams['metadata'] = $metadata;
            $sessionParams['expires_at'] = $stripeExpiresAt;
            $sessionOptions['idempotency_key'] = StripeCheckoutContract::idempotencyKey(
                (int) $pending['attempt']['attempt_id'],
                'checkout-session',
            );
        }

        try {
            $session = \Stripe\Checkout\Session::create($sessionParams, $sessionOptions);
        } catch (\Throwable $e) {
            $this->trackAmbiguousStripeError($pending, $e);
            flash('error', 'Impossible de préparer le paiement Stripe. Veuillez réessayer.');
            redirect('/panier');
        }

        if (isset($pending['attempt'])) {
            try {
                PaymentAttemptModel::bindStripeSession((int) $pending['attempt']['attempt_id'], $session->id);
            } catch (\Throwable $e) {
                error_log('[payment] liaison session Stripe impossible: ' . $e->getMessage());
                flash('error', 'Impossible de finaliser la préparation du paiement. Veuillez réessayer.');
                redirect('/panier');
            }
        }

        $_SESSION['stripe_session_id'] = $session->id;
        header('Location: ' . $session->url);
        exit;
    }

    public function cancel(): void
    {
        if (isset($_SESSION['stripe_draft_id'])) {
            try {
                PaymentAttemptModel::markDraftStatus((int) $_SESSION['stripe_draft_id'], 'cancelled');
                if (isset($_SESSION['stripe_attempt_id'])) {
                    PaymentAttemptModel::markAttemptStatus((int) $_SESSION['stripe_attempt_id'], 'cancelled');
                }
            } catch (\Throwable $e) {
                error_log('[payment] annulation tracking draft impossible: ' . $e->getMessage());
            }
        }

        unset($_SESSION['stripe_session_id'], $_SESSION['stripe_draft_id'], $_SESSION['stripe_attempt_id']);
        flash('error', 'Paiement annulé. Votre commande n\'a pas été enregistrée.');
        redirect('/panier');
    }

    private function preparePersistedAttempt(array $pending): array
    {
        $user = currentUser();
        StripeCheckoutContract::assertCompatible($pending['draft'], $pending['attempt'], (int) $user['id']);

        $attempt = $pending['attempt'];
        if (in_array((string) $attempt['status'], ['failed', 'cancelled'], true)) {
            return $this->replaceAttempt($pending);
        }

        if (!empty($attempt['provider_session_id'])) {
            try {
                $session = \Stripe\Checkout\Session::retrieve((string) $attempt['provider_session_id']);
            } catch (\Throwable $e) {
                error_log('[payment] lecture Checkout Session existante impossible: ' . $e->getMessage());
                throw new \RuntimeException('Checkout Session Stripe temporairement indisponible.', 0, $e);
            }

            if ((string) $session->status === 'expired') {
                PaymentAttemptModel::failAttempt((int) $attempt['attempt_id'], 'Checkout Session Stripe expirée.');
                return $this->replaceAttempt($pending);
            }

            $pending['stripe_session'] = $session;
        }

        return $pending;
    }

    private function replaceAttempt(array $pending): array
    {
        $attemptId = PaymentAttemptModel::createRetryAttempt((int) $pending['draft']['draft_id']);
        $attempt = PaymentAttemptModel::findAttemptForDraft($attemptId, (int) $pending['draft']['draft_id']);
        if (!$attempt) {
            throw new \RuntimeException('Nouvelle tentative Stripe introuvable.');
        }

        StripeCheckoutContract::assertCompatible(
            $pending['draft'],
            $attempt,
            (int) currentUser()['id'],
        );

        $_SESSION['stripe_attempt_id'] = $attemptId;
        unset($_SESSION['stripe_session_id']);
        $pending['attempt'] = $attempt;
        unset($pending['stripe_session']);

        return $pending;
    }

    private function redirectToExistingSession(array $pending): bool
    {
        if (!isset($pending['stripe_session'])) {
            return false;
        }

        $session = $pending['stripe_session'];
        if ((string) $session->status === 'open' && !empty($session->url)) {
            $_SESSION['stripe_session_id'] = $session->id;
            header('Location: ' . $session->url);
            exit;
        }

        if ((string) $session->status === 'complete') {
            redirect('/stripe/success?session_id=' . rawurlencode((string) $session->id));
        }

        return false;
    }

    private function trackAmbiguousStripeError(array $pending, \Throwable $e): void
    {
        if (isset($pending['attempt'])) {
            try {
                PaymentAttemptModel::recordAttemptError((int) $pending['attempt']['attempt_id'], $e->getMessage());
            } catch (\Throwable $trackingError) {
                error_log('[payment] tracking erreur Stripe impossible: ' . $trackingError->getMessage());
            }
        }

        error_log('[payment] appel Stripe ambigu: ' . $e->getMessage());
    }

    private function loadPendingPayment(): ?array
    {
        $user = currentUser();
        $draftId = (int) ($_SESSION['stripe_draft_id'] ?? 0);

        if ($draftId <= 0 || !$user) {
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

            $attemptId = (int) ($_SESSION['stripe_attempt_id'] ?? 0);
            $attempt = $attemptId > 0
                ? PaymentAttemptModel::findAttemptForDraft($attemptId, $draftId)
                : PaymentAttemptModel::latestAttemptForDraft($draftId);
            if (!$attempt || (string) $attempt['provider'] !== 'stripe') {
                return null;
            }

            return [
                'commande_data' => $draft['commande_data'],
                'pricing' => $draft['pricing'],
                'panier' => $draft['panier'],
                'draft' => $draft,
                'attempt' => $attempt,
            ];
        } catch (\Throwable $e) {
            error_log('[payment] lecture draft impossible draft_id=' . $draftId . ': ' . $e->getMessage());
            return null;
        }
    }
}
