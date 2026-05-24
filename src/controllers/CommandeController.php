<?php
// src/controllers/CommandeController.php

class CommandeController {

    public function calculLivraison(): void {
        header('Content-Type: application/json; charset=utf-8');
        $ville = sanitize($_GET['ville'] ?? '');
        if (!$ville || strtolower(trim($ville)) === 'bordeaux') {
            echo json_encode(['ok' => true, 'distance' => 0, 'prix' => 0]);
            return;
        }

        $distance = distanceKmDepuisBordeaux($ville);
        if ($distance <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Distance de livraison impossible à calculer.']);
            return;
        }

        echo json_encode(['ok' => true, 'distance' => $distance, 'prix' => calculPrixLivraison($ville)]);
    }

    public function create(): void {
        requireAuth();
        verifyCsrf();
        $user  = currentUser();
        $panier = $_SESSION['panier'] ?? [];

        if (empty($panier)) {
            flash('error', 'Votre panier est vide.');
            redirect('/panier');
        }

        $totalMenus = array_sum(array_column($panier, 'prix_menu'));

        try {
            $payload = CommandeService::payloadFromRequest($_POST, (float)$totalMenus);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/panier');
        }

        $numeroCommande = generateNumeroCommande();

        $commandeData = $payload + [
            'numero_commande' => $numeroCommande,
            'utilisateur_id'  => $user['id'],
        ];

        // Distribute livraison cost on first ligne only
        $lignes = [];
        $livraisonApplied = false;
        foreach ($panier as $item) {
            $prixLivraison = (!$livraisonApplied) ? (float)$payload['prix_livraison'] : 0.0;
            $livraisonApplied = true;
            $lignes[] = [
                'menu_id'         => (int)$item['menu_id'],
                'nombre_personne' => (int)$item['nombre_personne'],
                'prix_menu'       => (float)$item['prix_menu'],
                'prix_livraison'  => $prixLivraison,
                'prix_total_ligne'=> round((float)$item['prix_menu'] + $prixLivraison, 2),
            ];
        }

        try {
            $commandeId = CommandeModel::create($commandeData, $lignes);
        } catch (\Throwable $e) {
            flash('error', 'Un ou plusieurs menus ne sont plus disponibles.');
            redirect('/panier');
        }

        foreach ($panier as $item) {
            $menu = MenuModel::getById((int)$item['menu_id']);
            if ($menu) {
                StatsService::recordCommande($commandeId, [
                    'menu_id'        => $item['menu_id'],
                    'prix_total'     => $item['prix_menu'],
                    'nombre_personne'=> $item['nombre_personne'],
                ], $menu);
            }
        }

        $userFull = \UserModel::findById($user['id']);
        MailService::sendCommandeConfirmation($userFull['email'], $commandeData, $panier);

        $_SESSION['panier'] = [];

        flash('success', 'Commande #' . $numeroCommande . ' passée avec succès !');
        redirect('/mon-compte');
    }

    public function update(): void {
        requireAuth();
        verifyCsrf();
        $user       = currentUser();
        $commande = $this->currentUserCommande((int)($_POST['commande_id'] ?? 0), $user['id']);
        if (!$commande) {
            $this->redirectCommandeIntrouvable();
        }
        if (!CommandeModel::canModify($commande)) {
            flash('error', 'Cette commande ne peut plus être modifiée.'); redirect('/mon-compte');
        }

        $lignes = CommandeModel::getLignes((int)$commande['commande_id']);
        $totalMenus = array_sum(array_column($lignes, 'prix_menu'));

        try {
            $payload = CommandeService::payloadFromRequest($_POST, (float)$totalMenus);
        } catch (InvalidArgumentException $e) {
            redirect('/mon-compte?open_modal=modif_' . (int)$commande['commande_id'] . '&modal_error=' . urlencode($e->getMessage()));
        }

        CommandeModel::updateDetails((int)$commande['commande_id'], $payload);

        $msg = 'Commande modifiée. Nouveau total : ' . formatPrice($payload['prix_total']);
        if (abs($payload['prix_total'] - (float)$commande['prix_total']) > 0.01) {
            $msg .= ' (ancien total : ' . formatPrice($commande['prix_total']) . ')';
        }
        flash('success', $msg);
        redirect('/mon-compte');
    }

    public function cancel(): void {
        requireAuth();
        verifyCsrf();
        $user       = currentUser();
        $commande = $this->currentUserCommande((int)($_POST['commande_id'] ?? 0), $user['id']);
        if (!$commande) {
            $this->redirectCommandeIntrouvable();
        }
        if (!CommandeModel::canModify($commande)) {
            flash('error', 'Impossible d\'annuler cette commande.'); redirect('/mon-compte');
        }

        CommandeModel::cancel((int)$commande['commande_id'], 'Annulation demandée par le client', 'client', $user['id']);
        flash('success', 'Commande annulée.');
        redirect('/mon-compte');
    }

    public function suivi(): void {
        requireAuth();
        $user       = currentUser();
        $commande = $this->currentUserCommande((int)($_GET['id'] ?? 0), $user['id']);
        if (!$commande) {
            $this->redirectCommandeIntrouvable();
        }

        $historique = CommandeModel::getHistorique((int)$commande['commande_id']);
        view('pages/commande/suivi', compact('commande', 'historique'));
    }

    private function currentUserCommande(int $commandeId, int $userId): ?array {
        $commande = CommandeModel::getById($commandeId);
        if (!$commande || (int)$commande['utilisateur_id'] !== $userId) {
            return null;
        }
        return $commande;
    }

    private function redirectCommandeIntrouvable(): void {
        flash('error', 'Commande introuvable.');
        redirect('/mon-compte');
    }
}
