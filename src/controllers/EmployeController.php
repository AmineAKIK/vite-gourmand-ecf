<?php
// src/controllers/EmployeController.php

class EmployeController
{
    public function dashboard(): void
    {
        if (hasRole(ROLE_ADMIN)) {
            redirect('/admin');
        }

        $toutesCommandes     = CommandeModel::getAll();
        $commandesEnAttente  = CommandeModel::getAll(['statut' => 'en_attente']);
        $avisEnAttente       = AvisModel::getPending();
        $activiteRecente     = array_slice($toutesCommandes, 0, 5);

        $today           = date('Y-m-d');
        $lundiSemaine    = date('Y-m-d', strtotime('monday this week'));
        $commandesAujourdhui = array_filter($toutesCommandes, fn($c) => str_starts_with($c['date_commande'] ?? '', $today));
        $commandesSemaine    = array_filter($toutesCommandes, fn($c) => ($c['date_commande'] ?? '') >= $lundiSemaine);

        view('pages/employe/dashboard', compact(
            'commandesEnAttente', 'avisEnAttente',
            'commandesAujourdhui', 'commandesSemaine',
            'activiteRecente'
        ));
    }

    public function commandes(): void
    {
        $filters = [
            'statut' => $_GET['statut'] ?? null,
            'client' => $_GET['client'] ?? null,
        ];
        $commandes = CommandeModel::getAll($filters);
        $statuts   = commandeStatuses();
        view('pages/employe/commandes', compact('commandes', 'filters', 'statuts'));
    }

    public function updateStatut(): void
    {
        verifyCsrf();

        $user        = currentUser();
        $commandeId  = (int)($_POST['commande_id'] ?? 0);
        $statut      = sanitize($_POST['statut']      ?? '');
        $commentaire = sanitize($_POST['commentaire'] ?? '');
        $action      = sanitize($_POST['action']      ?? '');

        $commande = CommandeModel::getById($commandeId);
        if (!$commande) {
            flash('error', 'Commande introuvable.');
            redirect('/employe/commandes');
        }

        if (!commandeStatusIsValid($statut)) {
            flash('error', 'Statut invalide.');
            redirect('/employe/commandes');
        }

        if (!commandeCanTransition($commande['statut'] ?? null, $statut)) {
            flash('error', 'Transition de statut non autorisée.');
            redirect('/employe/commandes');
        }

        if ($action === 'annuler' || $statut === commandeCancelledStatus()) {
            $motif       = sanitize($_POST['commentaire']  ?? '');
            $modeContact = sanitize($_POST['mode_contact'] ?? '');
            if (!$motif || !$modeContact) {
                flash('error', 'Le motif et le mode de contact sont obligatoires pour une annulation.');
                redirect('/employe/commandes');
            }
            CommandeModel::cancel($commandeId, $motif, $modeContact, $user['id']);
        } else {
            CommandeModel::updateStatut($commandeId, $statut, $commentaire ?: null, $user['id']);
        }

        $userCommande = \UserModel::findById($commande['utilisateur_id']);
        if ($statut === commandeCompletedStatus() && $userCommande) {
            MailService::sendCommandeTerminee($userCommande['email'], $commandeId);
        }
        if ($statut === commandeAwaitingMaterialStatus() && $userCommande) {
            MailService::sendMaterielRelance($userCommande['email'], $userCommande['prenom']);
        }

        flash('success', 'Statut mis à jour.');
        redirect('/employe/commandes');
    }

    public function menus(): void
    {
        $menus       = MenuModel::getAll();
        $themes      = MenuModel::getThemes();
        $regimes     = MenuModel::getRegimes();
        $plats       = MenuModel::getPlatsForAdmin();
        $categories  = MenuModel::getCategories();
        $platsByMenu = MenuModel::getPlatsByMenu();
        $imagesByMenu = MenuModel::getImagesByMenuIds(array_column($menus, 'menu_id'));

        view('pages/employe/menus', compact('menus', 'themes', 'regimes', 'plats', 'categories', 'platsByMenu', 'imagesByMenu'));
    }

    public function createMenu(): void
    {
        verifyCsrf();

        try {
            $data = MenuAdminService::menuPayloadFromRequest($_POST);
        } catch (InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=creer_menu&modal_error=' . urlencode($e->getMessage()));
        }

        $images = $_FILES['images'] ?? [];
        if (empty($images['name'][0]) || ($images['error'][0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            redirect('/employe/menus?open_modal=creer_menu&modal_error=' . urlencode('Au moins une photo est obligatoire.'));
        }

        $menuId = MenuModel::create($data);
        MenuModel::addMenuPlats($menuId, MenuAdminService::selectedIds($_POST, 'plats'));
        MenuAdminService::uploadMenuImages($menuId, $images, 1);

        flash('success', 'Menu créé avec succès.');
        redirect('/employe/menus');
    }

    public function updateMenu(): void
    {
        verifyCsrf();

        $id = (int)($_POST['menu_id'] ?? 0);
        if (!$id) {
            flash('error', 'Menu introuvable.');
            redirect('/employe/menus');
        }

        try {
            $data = MenuAdminService::menuPayloadFromRequest($_POST);
        } catch (InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=modifier_menu_' . $id . '&modal_error=' . urlencode($e->getMessage()));
        }

        MenuModel::update($id, $data);
        MenuModel::replaceMenuPlats($id, MenuAdminService::selectedIds($_POST, 'plats'));
        MenuAdminService::uploadMenuImages($id, $_FILES['images'] ?? [], MenuModel::nextMenuImageOrder($id));

        flash('success', 'Menu modifié avec succès.');
        redirect('/employe/menus');
    }

    public function deleteMenu(): void
    {
        verifyCsrf();
        MenuModel::delete((int)($_POST['menu_id'] ?? 0));
        flash('success', 'Menu supprimé.');
        redirect('/employe/menus');
    }

    public function createPlat(): void
    {
        verifyCsrf();

        try {
            $data = MenuAdminService::platPayloadFromRequest($_POST);
        } catch (InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=creer_plat&modal_error=' . urlencode($e->getMessage()));
        }

        MenuModel::createPlat($data);

        flash('success', 'Plat créé avec succès.');
        redirect('/employe/menus');
    }

    public function updatePlat(): void
    {
        verifyCsrf();

        $id = (int)($_POST['plat_id'] ?? 0);
        if (!$id) {
            flash('error', 'Plat introuvable.');
            redirect('/employe/menus');
        }

        try {
            $data = MenuAdminService::platPayloadFromRequest($_POST);
        } catch (InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=modifier_plat_' . $id . '&modal_error=' . urlencode($e->getMessage()));
        }

        MenuModel::updatePlat($id, $data);

        flash('success', 'Plat modifié.');
        redirect('/employe/menus');
    }

    public function deletePlat(): void
    {
        verifyCsrf();
        $platId = (int)($_POST['plat_id'] ?? 0);
        if (MenuModel::platIsUsed($platId)) {
            flash('error', 'Impossible de supprimer un plat utilisé dans un menu. Retirez-le d\'abord des menus concernés.');
            redirect('/employe/menus');
        }

        MenuModel::deletePlat($platId);
        flash('success', 'Plat supprimé.');
        redirect('/employe/menus');
    }

    public function deleteMenuImage(): void
    {
        verifyCsrf();
        MenuAdminService::deleteMenuImageFile((int)($_POST['image_id'] ?? 0));
        flash('success', 'Image supprimée.');
        redirect('/employe/menus');
    }

    public function avis(): void
    {
        $filtre  = in_array($_GET['filtre'] ?? '', ['en_attente', 'valide', 'refuse']) ? $_GET['filtre'] : 'en_attente';
        $avis    = AvisModel::getAll($filtre);
        $pending = AvisModel::getPending();

        view('pages/employe/avis', compact('avis', 'filtre', 'pending'));
    }

    public function validerAvis(): void
    {
        verifyCsrf();
        $commandeId = (int)($_POST['commande_id'] ?? 0);
        $action     = sanitize($_POST['action']   ?? '');
        $filtre     = sanitize($_POST['filtre']   ?? 'en_attente');
        $statut     = ($action === 'valider') ? 'valide' : 'refuse';

        AvisModel::updateStatusByCommande($commandeId, $statut);

        flash('success', 'Avis ' . ($statut === 'valide' ? 'validé' : 'refusé') . '.');
        redirect('/employe/avis?filtre=' . urlencode($filtre));
    }

    public function supprimerAvis(): void
    {
        verifyCsrf();
        $avisId = (int)($_POST['avis_id'] ?? 0);
        $filtre = sanitize($_POST['filtre'] ?? 'en_attente');

        if ($avisId) {
            AvisModel::delete($avisId);
            flash('success', 'Avis supprimé définitivement.');
        }
        redirect('/employe/avis?filtre=' . urlencode($filtre));
    }

    public function horaires(): void
    {
        $horaires = HoraireModel::getAll();
        view('pages/employe/horaires', compact('horaires'));
    }

    public function updateHoraires(): void
    {
        verifyCsrf();

        HoraireModel::updateMany($_POST['horaires'] ?? []);

        flash('success', 'Horaires mis à jour.');
        redirect('/employe/horaires');
    }
}
