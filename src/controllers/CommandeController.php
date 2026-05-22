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

    public function form(): void {
        $menus = MenuModel::getAll();
        $menuPreselect = isset($_GET['menu_id']) ? MenuModel::getById((int)$_GET['menu_id']) : null;
        $userSession = currentUser();
        $user = \UserModel::findById($userSession['id']) ?: $userSession;
        view('pages/commande/form', compact('menus', 'menuPreselect', 'user'));
    }

    public function create(): void {
        requireAuth();
        verifyCsrf();
        $user = currentUser();

        $menuId       = (int)($_POST['menu_id'] ?? 0);
        $menu         = MenuModel::getById($menuId);

        if (!$menu) { flash('error', 'Menu introuvable.'); redirect('/commande'); }
        if ($menu['quantite_restante'] !== null && $menu['quantite_restante'] <= 0) {
            flash('error', 'Ce menu n\'est plus disponible.'); redirect('/menus');
        }

        try {
            $payload = CommandeService::payloadFromRequest($_POST, $menu);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/commande?menu_id=' . $menuId);
        }

        $data = $payload + [
            'numero_commande'       => generateNumeroCommande(),
            'utilisateur_id'        => $user['id'],
            'menu_id'               => $menuId,
        ];

        try {
            $commandeId = CommandeModel::create($data);
        } catch (\Throwable $e) {
            flash('error', 'Ce menu n\'est plus disponible.');
            redirect('/menus');
        }

        StatsService::recordCommande($commandeId, $data, $menu);

        // Mail de confirmation
        $userFull = \UserModel::findById($user['id']);
        MailService::sendCommandeConfirmation($userFull['email'], $data, $menu);

        flash('success', 'Commande #' . $data['numero_commande'] . ' passée avec succès !');
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

        $menu = MenuModel::getById((int)$commande['menu_id']);
        if (!$menu) {
            flash('error', 'Menu introuvable.'); redirect('/mon-compte');
        }

        try {
            $payload = CommandeService::payloadFromRequest($_POST, $menu);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/mon-compte');
        }

        CommandeModel::updateDetails((int)$commande['commande_id'], $payload);

        flash('success', 'Commande modifiée.');
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
