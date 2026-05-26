<?php
// src/controllers/StripeController.php

class StripeController {

    public function checkout(): void {
        requireAuth();

        $pending = $_SESSION['stripe_pending'] ?? null;
        if (!$pending) {
            flash('error', 'Session expirée. Veuillez recommencer votre commande.');
            redirect('/panier');
        }

        if (!STRIPE_SECRET_KEY || str_starts_with(STRIPE_SECRET_KEY, 'sk_test_REMPLACER')) {
            flash('error', 'Le paiement en ligne n\'est pas encore configuré. Choisissez un autre mode de paiement.');
            redirect('/panier');
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        $commandeData = $pending['commande_data'];
        $pricing      = $pending['pricing'];
        $panier       = $pending['panier'];

        $lineItems = [];

        // Ligne par menu dans la commande
        foreach ($pricing['lignes'] as $ligne) {
            $menu = \MenuModel::getById((int)$ligne['menu_id']);
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => (int)round($ligne['prix_menu'] * 100),
                    'product_data' => [
                        'name' => ($menu['titre'] ?? 'Menu') . ' × ' . $ligne['nombre_personne'] . ' pers.',
                    ],
                ],
                'quantity' => 1,
            ];
        }

        // Frais de livraison si non nuls
        if ($pricing['prix_livraison'] > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => (int)round($pricing['prix_livraison'] * 100),
                    'product_data' => ['name' => 'Frais de livraison'],
                ],
                'quantity' => 1,
            ];
        }

        // Remise globale si applicable
        $discounts = [];
        if (!empty($pricing['remise_globale']) && $pricing['remise_globale'] > 0) {
            $coupon = \Stripe\Coupon::create([
                'amount_off' => (int)round($pricing['remise_globale'] * 100),
                'currency'   => 'eur',
                'duration'   => 'once',
                'name'       => 'Réduction fidélité',
            ]);
            $discounts = [['coupon' => $coupon->id]];
        }

        $baseUrl = rtrim(BASE_URL, '/');

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'discounts'            => $discounts,
            'mode'                 => 'payment',
            'success_url'          => $baseUrl . '/stripe/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => $baseUrl . '/stripe/cancel',
            'metadata'             => [
                'numero_commande' => $commandeData['numero_commande'],
                'utilisateur_id'  => (string)$commandeData['utilisateur_id'],
            ],
            'client_reference_id' => $commandeData['numero_commande'],
        ]);

        $_SESSION['stripe_session_id'] = $session->id;

        header('Location: ' . $session->url);
        exit;
    }

    public function success(): void {
        requireAuth();

        $sessionId = sanitize($_GET['session_id'] ?? '');
        $pending   = $_SESSION['stripe_pending'] ?? null;

        if (!$pending || !$sessionId) {
            flash('error', 'Paiement non confirmé.');
            redirect('/mon-compte');
        }

        // Verify the Stripe session is actually paid
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
        $pricing      = $pending['pricing'];
        $panier       = $pending['panier'];

        try {
            $commandeId = \CommandeModel::create($commandeData, $pricing['lignes']);
        } catch (\Throwable $e) {
            flash('error', 'Erreur lors de la création de la commande. Contactez-nous avec votre référence de paiement Stripe : ' . $sessionId);
            redirect('/mon-compte');
        }

        // Enregistrer le paiement Stripe
        $user = currentUser();
        \PaiementModel::create([
            'commande_id'   => $commandeId,
            'type_paiement' => 'paiement_unique',
            'montant'       => $commandeData['prix_total'],
            'mode'          => 'cb_online',
            'date_paiement' => date('Y-m-d'),
            'reference'     => $stripeSession->payment_intent ?? $sessionId,
            'note'          => 'Paiement Stripe — session ' . $sessionId,
        ], (int)$user['id']);

        $userFull = \UserModel::findById($user['id']);
        \MailService::sendCommandeConfirmation($userFull['email'], $commandeData, $panier);

        unset($_SESSION['stripe_pending'], $_SESSION['stripe_session_id']);
        $_SESSION['panier'] = [];

        flash('success', 'Paiement confirmé ! Commande #' . $commandeData['numero_commande'] . ' passée avec succès.');
        redirect('/mon-compte');
    }

    public function cancel(): void {
        unset($_SESSION['stripe_session_id']);
        flash('error', 'Paiement annulé. Votre commande n\'a pas été enregistrée.');
        redirect('/panier');
    }

    public function webhook(): void {
        $payload   = file_get_contents('php://input');
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

        // Handle checkout.session.completed as a safety net
        // (primary confirmation is via /stripe/success redirect)
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $ref     = $session->client_reference_id;

            // Check if order already exists (created by success redirect)
            $commande = db()->fetchOne(
                "SELECT commande_id, prix_total FROM commande WHERE numero_commande = ?",
                [$ref]
            );

            if ($commande) {
                // Ensure paiement is recorded if somehow missing
                $already = db()->fetchOne(
                    "SELECT paiement_id FROM paiement WHERE commande_id = ? AND mode = 'cb_online'",
                    [$commande['commande_id']]
                );
                if (!$already) {
                    \PaiementModel::create([
                        'commande_id'   => $commande['commande_id'],
                        'type_paiement' => 'paiement_unique',
                        'montant'       => $commande['prix_total'],
                        'mode'          => 'cb_online',
                        'date_paiement' => date('Y-m-d'),
                        'reference'     => $session->payment_intent ?? $session->id,
                        'note'          => 'Paiement Stripe via webhook — session ' . $session->id,
                    ], null);
                }
            }
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }
}
