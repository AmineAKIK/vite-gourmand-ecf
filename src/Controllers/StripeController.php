<?php

namespace App\Controllers;

use App\Domain\StripeCheckoutContract;
use App\Models\CommandeModel;
use App\Models\MenuModel;
use App\Models\PaiementModel;
use App\Models\PaymentAttemptModel;
use App\Models\UserModel;
use App\Services\MailService;

class StripeController
{
    public function checkout(): void
    {
        requireAuth();

        $pending = $this->loadPendingPayment();
        if (!$pending) {
            flash('error', 'Session expirée. Veuillez recommencer votre commande.');
            redirect('/panier');
        }

        if (!STRIPE_SECRET_KEY || str_starts_with(STRIPE_SECRET_KEY, 'sk_test_REMPLACER')) {
            flash('error', 'Le paiement en ligne n\'est pas encore configuré. Choisissez un autre mode de paiement.');
            redirect('/panier');
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        if (isset($pending['draft'], $pending['attempt'])) {
            $pending = $this->preparePersistedAttempt($pending);
            if ($this->redirectToExistingOpenSession($pending['attempt'])) {
                return;
            }
        }

        $commandeData = $pending['commande_data'];
        $pricing = $pending['pricing'];
        $currency = strtolower((string) ($pricing['currency'] ?? 'eur'));

        $expectedCents = (int) ($pricing['total_ttc_cents'] ?? round($pricing['total_ttc'] * 100));
        $grossCents = (int) ($pricing['total_brut_cents'] ?? round($pricing['total_brut'] * 100));
        $deliveryCents = (int) ($pricing['prix_livraison_cents'] ?? round($pricing['prix_livraison'] * 100));
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

        $lineItems = [];
        foreach ($pricing['lignes'] as $ligne) {
            $menu = MenuModel::getById((int) $ligne['menu_id']);
            $lineGrossCents = (int) ($ligne['prix_menu_brut_cents'] ?? round(
                ((float) $ligne['prix_par_personne_snapshot']) * ((int) $ligne['nombre_personne']) * 100,
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

            $coupon = \Stripe\Coupon::create([
                'amount_off' => $discountCents,
                'currency' => $currency,
                'duration' => 'once',
                'name' => 'Réduction fidélité',
            ], $couponOptions);
            $discounts = [['coupon' => $coupon->id]];
        }

        $baseUrl = rtrim(BASE_URL, '/');
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
            $sessionParams['expires_at'] = StripeCheckoutContract::sessionExpiresAt(
                (string) $pending['draft']['expires_at'],
            );
            $sessionOptions['idempotency_key'] = StripeCheckoutContract::idempotencyKey(
                (int) $pending['attempt']['attempt_id'],
                'checkout-session',
            );
        }

        try {
            $session = \Stripe\Checkout\Session::create($sessionParams, $sessionOptions);
        } catch (\Throwable $e) {
            if (isset($pending['attempt'])) {
                try {
                    PaymentAttemptModel::recordAttemptError((int) $pending['attempt']['attempt_id'], $e->getMessage());
                } catch (\Throwable $trackingError) {
                    error_log('[payment] tracking erreur Stripe impossible: ' . $trackingError->getMessage());
                }
            }
            error_log('[payment] création Checkout Session Stripe impossible: ' . $e->getMessage());
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

    public function success(): void
    {
        requireAuth();

        $sessionId = sanitize($_GET['session_id'] ?? '');
        $pending = $this->loadPendingPayment();

        if (!$pending || !$sessionId) {
            flash('error', 'Paiement non confirmé.');
            redirect('/mon-compte');
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        try {
            $stripeSession = \Stripe\Checkout\Session::retrieve($sessionId);
        } catch (\Throwable $e) {
            flash('error', 'Impossible de vérifier le paiement. Contactez-nous.');
            redirect('/mon-compte');
        }

        if ($stripeSession->payment_status !== 'paid') {
            flash('error', 'Le paiement n\'a pas été complété.');
            redirect('/panier');
        }

        $commandeData = $pending['commande_data'];
        $pricing = $pending['pricing'];
        $panier = $pending['panier'];

        try {
            $commandeId = CommandeModel::create($commandeData, $pricing['lignes']);
        } catch (\Throwable $e) {
            flash('error', 'Erreur lors de la création de la commande. Contactez-nous avec votre référence de paiement Stripe : ' . $sessionId);
            redirect('/mon-compte');
        }

        $user = currentUser();

        try {
            \App\Models\StockModel::consommerPourCommande($commandeId, (int) $user['id']);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[stock] consommation impossible pour commande_id=%d via Stripe: %s',
                $commandeId,
                $e->getMessage(),
            ));
        }

        PaiementModel::create([
            'commande_id' => $commandeId,
            'type_paiement' => 'paiement_unique',
            'montant' => $commandeData['prix_total'],
            'mode' => 'cb_online',
            'date_paiement' => date('Y-m-d'),
            'reference' => $stripeSession->payment_intent ?? $sessionId,
            'note' => 'Paiement Stripe — session ' . $sessionId,
        ], (int) $user['id']);

        if (isset($pending['draft'], $pending['attempt'])) {
            try {
                PaymentAttemptModel::markAttemptStatus(
                    (int) $pending['attempt']['attempt_id'],
                    'paid',
                    isset($stripeSession->payment_intent) ? (string) $stripeSession->payment_intent : null,
                );
                PaymentAttemptModel::attachCommande((int) $pending['draft']['draft_id'], $commandeId);
            } catch (\Throwable $e) {
                error_log('[payment] finalisation tracking draft impossible commande_id=' . $commandeId . ': ' . $e->getMessage());
            }
        }

        $userFull = UserModel::findById($user['id']);
        MailService::sendCommandeConfirmation($userFull['email'], $commandeData, $panier);

        unset(
            $_SESSION['stripe_pending'],
            $_SESSION['stripe_draft_id'],
            $_SESSION['stripe_attempt_id'],
            $_SESSION['stripe_session_id'],
        );
        $_SESSION['panier'] = [];

        flash('success', 'Paiement confirmé ! Commande #' . $commandeData['numero_commande'] . ' passée avec succès.');
        redirect('/mon-compte');
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
        } catch (\Throwable $e) {
            http_response_code(400);
            exit;
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $ref = $session->client_reference_id;

            $commande = db()->fetchOne(
                'SELECT commande_id, prix_total FROM commande WHERE numero_commande = ?',
                [$ref],
            );

            if ($commande) {
                $already = db()->fetchOne(
                    "SELECT paiement_id FROM paiement WHERE commande_id = ? AND mode = 'cb_online'",
                    [$commande['commande_id']],
                );
                if (!$already) {
                    PaiementModel::create([
                        'commande_id' => $commande['commande_id'],
                        'type_paiement' => 'paiement_unique',
                        'montant' => $commande['prix_total'],
                        'mode' => 'cb_online',
                        'date_paiement' => date('Y-m-d'),
                        'reference' => $session->payment_intent ?? $session->id,
                        'note' => 'Paiement Stripe via webhook — session ' . $session->id,
                    ], null);
                }
            }
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
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
                flash('error', 'Impossible de vérifier votre paiement en cours. Veuillez réessayer.');
                redirect('/panier');
            }

            if ((string) $session->status === 'expired') {
                PaymentAttemptModel::recordAttemptError((int) $attempt['attempt_id'], 'Checkout Session Stripe expirée.');
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

        $_SESSION['stripe_attempt_id'] = $attemptId;
        unset($_SESSION['stripe_session_id']);
        $pending['attempt'] = $attempt;
        unset($pending['stripe_session']);

        return $pending;
    }

    private function redirectToExistingOpenSession(array $attempt): bool
    {
        if (empty($attempt['provider_session_id'])) {
            return false;
        }

        try {
            $session = \Stripe\Checkout\Session::retrieve((string) $attempt['provider_session_id']);
        } catch (\Throwable $e) {
            error_log('[payment] relecture Checkout Session impossible: ' . $e->getMessage());
            flash('error', 'Impossible de reprendre votre paiement en cours. Veuillez réessayer.');
            redirect('/panier');
        }

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

    private function loadPendingPayment(): ?array
    {
        $user = currentUser();
        $draftId = (int) ($_SESSION['stripe_draft_id'] ?? 0);

        if ($draftId > 0 && $user) {
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

        $legacy = $_SESSION['stripe_pending'] ?? null;

        return is_array($legacy) ? $legacy : null;
    }
}
