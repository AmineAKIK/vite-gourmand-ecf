<?php
// src/controllers/CommandeController.php

require_once __DIR__ . '/../models/CommandeModel.php';
require_once __DIR__ . '/../models/MenuModel.php';
require_once __DIR__ . '/../services/MailService.php';

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

        echo json_encode([
            'ok' => true,
            'distance' => $distance,
            'prix' => round(LIVRAISON_BASE + (LIVRAISON_KM * $distance), 2),
        ]);
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

        $nbPersonnes  = (int)($_POST['nombre_personne'] ?? 0);
        if ($nbPersonnes < $menu['nombre_personne_minimum']) {
            flash('error', 'Nombre de personnes insuffisant (minimum : ' . $menu['nombre_personne_minimum'] . ').');
            redirect('/commande?menu_id=' . $menuId);
        }

        $datePrestation = sanitize($_POST['date_prestation'] ?? '');
        $heureLivraison = sanitize($_POST['heure_livraison'] ?? '');
        $adresse        = sanitize($_POST['adresse_livraison'] ?? '');
        $ville          = sanitize($_POST['ville_livraison'] ?? '');
        $codePostal     = sanitize($_POST['code_postal_livraison'] ?? '');

        if (!$datePrestation || !$heureLivraison || !$adresse || !$ville || !$codePostal) {
            flash('error', 'Tous les champs de livraison sont obligatoires.');
            redirect('/commande?menu_id=' . $menuId);
        }
        if ($datePrestation < date('Y-m-d', strtotime('+1 day'))) {
            flash('error', 'La date de prestation doit être au minimum demain.');
            redirect('/commande?menu_id=' . $menuId);
        }
        if (strtolower(trim($ville)) !== 'bordeaux' && distanceKmDepuisBordeaux($ville) <= 0) {
            flash('error', 'Distance de livraison impossible à calculer pour cette ville.');
            redirect('/commande?menu_id=' . $menuId);
        }

        $prixMenu     = calculPrixMenu($menu['prix_par_personne'], $nbPersonnes, $menu['nombre_personne_minimum']);
        $prixLivraison= calculPrixLivraison($ville);
        $prixTotal    = $prixMenu + $prixLivraison;

        $data = [
            'numero_commande'       => generateNumeroCommande(),
            'utilisateur_id'        => $user['id'],
            'menu_id'               => $menuId,
            'date_prestation'       => $datePrestation,
            'heure_livraison'       => $heureLivraison,
            'adresse_livraison'     => $adresse,
            'ville_livraison'       => $ville,
            'code_postal_livraison' => $codePostal,
            'nombre_personne'       => $nbPersonnes,
            'prix_menu'             => $prixMenu,
            'prix_livraison'        => $prixLivraison,
            'prix_total'            => $prixTotal,
        ];

        try {
            $commandeId = CommandeModel::create($data);
        } catch (\Throwable $e) {
            flash('error', 'Ce menu n\'est plus disponible.');
            redirect('/menus');
        }

        try {
            $mongoUri = defined('MONGO_URI') ? MONGO_URI : ($_ENV['MONGO_URI'] ?? null);
            if ($mongoUri && $mongoUri !== 'mongodb+srv://user:pass@cluster.mongodb.net' && class_exists(\MongoDB\Client::class)) {
                $client     = new \MongoDB\Client($mongoUri);
                $collection = $client->selectCollection(MONGO_DB, 'commandes_stats');
                $collection->insertOne([
                    'commande_id'  => $commandeId,
                    'menu_id'      => $data['menu_id'],
                    'menu_titre'   => $menu['titre'],
                    'prix_total'   => $data['prix_total'],
                    'nb_personnes' => $data['nombre_personne'],
                    'created_at'   => new \MongoDB\BSON\UTCDateTime(),
                ]);
            }
        } catch (\Throwable $e) {
            // Log silencieux si MongoDB indisponible
            error_log('MongoDB insert failed: ' . $e->getMessage());
        }

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
        $commandeId = (int)($_POST['commande_id'] ?? 0);
        $commande   = CommandeModel::getById($commandeId);

        if (!$commande || $commande['utilisateur_id'] != $user['id']) {
            flash('error', 'Commande introuvable.'); redirect('/mon-compte');
        }
        if (!CommandeModel::canModify($commande)) {
            flash('error', 'Cette commande ne peut plus être modifiée.'); redirect('/mon-compte');
        }

        $menu = MenuModel::getById((int)$commande['menu_id']);
        if (!$menu) {
            flash('error', 'Menu introuvable.'); redirect('/mon-compte');
        }

        $datePrestation = sanitize($_POST['date_prestation'] ?? '');
        $heureLivraison = sanitize($_POST['heure_livraison'] ?? '');
        $adresse        = sanitize($_POST['adresse_livraison'] ?? '');
        $ville          = sanitize($_POST['ville_livraison'] ?? '');
        $codePostal     = sanitize($_POST['code_postal_livraison'] ?? '');
        $nbPersonnes    = (int)($_POST['nombre_personne'] ?? 0);

        if (!$datePrestation || !$heureLivraison || !$adresse || !$ville || !$codePostal) {
            flash('error', 'Tous les champs de livraison sont obligatoires.');
            redirect('/mon-compte');
        }
        if ($datePrestation < date('Y-m-d', strtotime('+1 day'))) {
            flash('error', 'La date de prestation doit être au minimum demain.');
            redirect('/mon-compte');
        }
        if ($nbPersonnes < (int)$menu['nombre_personne_minimum']) {
            flash('error', 'Nombre de personnes insuffisant (minimum : ' . (int)$menu['nombre_personne_minimum'] . ').');
            redirect('/mon-compte');
        }
        if (strtolower(trim($ville)) !== 'bordeaux' && distanceKmDepuisBordeaux($ville) <= 0) {
            flash('error', 'Distance de livraison impossible à calculer pour cette ville.');
            redirect('/mon-compte');
        }

        $prixMenu      = calculPrixMenu((float)$menu['prix_par_personne'], $nbPersonnes, (int)$menu['nombre_personne_minimum']);
        $prixLivraison = calculPrixLivraison($ville);
        $prixTotal     = $prixMenu + $prixLivraison;

        $db   = \Database::getConnection();
        $stmt = $db->prepare("
            UPDATE commande SET date_prestation=?, heure_livraison=?, adresse_livraison=?,
            ville_livraison=?, code_postal_livraison=?, nombre_personne=?,
            prix_menu=?, prix_livraison=?, prix_total=? WHERE commande_id=?
        ");
        $stmt->execute([
            $datePrestation, $heureLivraison, $adresse,
            $ville, $codePostal, $nbPersonnes,
            $prixMenu, $prixLivraison, $prixTotal,
            $commandeId
        ]);

        flash('success', 'Commande modifiée.');
        redirect('/mon-compte');
    }

    public function cancel(): void {
        requireAuth();
        verifyCsrf();
        $user       = currentUser();
        $commandeId = (int)($_POST['commande_id'] ?? 0);
        $commande   = CommandeModel::getById($commandeId);

        if (!$commande || $commande['utilisateur_id'] != $user['id']) {
            flash('error', 'Commande introuvable.'); redirect('/mon-compte');
        }
        if (!CommandeModel::canModify($commande)) {
            flash('error', 'Impossible d\'annuler cette commande.'); redirect('/mon-compte');
        }

        CommandeModel::cancel($commandeId, 'Annulation demandée par le client', 'client', $user['id']);
        flash('success', 'Commande annulée.');
        redirect('/mon-compte');
    }

    public function suivi(): void {
        requireAuth();
        $user       = currentUser();
        $commandeId = (int)($_GET['id'] ?? 0);
        $commande   = CommandeModel::getById($commandeId);

        if (!$commande || $commande['utilisateur_id'] != $user['id']) {
            flash('error', 'Commande introuvable.'); redirect('/mon-compte');
        }

        $historique = CommandeModel::getHistorique($commandeId);
        view('pages/commande/suivi', compact('commande', 'historique'));
    }
}
