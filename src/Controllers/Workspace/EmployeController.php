<?php

namespace App\Controllers\Workspace;

use App\Domain\OrderStatus;
use App\Models\AvisModel;
use App\Models\CommandeModel;
use App\Models\IngredientModel;
use App\Models\UserModel;
use App\Security\Guard;
use App\Services\MailService;

class EmployeController
{
    public function dashboard(): void
    {
        if (hasRole(ROLE_ADMIN)) {
            redirect('/admin');
        }

        $toutesCommandes    = CommandeModel::getAll();
        $commandesEnAttente = CommandeModel::getAll(['statut' => 'en_attente']);
        $avisEnAttente      = AvisModel::getPending();
        $alertesStock       = [];
        try { $alertesStock = IngredientModel::getSousSeuilAlerte(); } catch (\Throwable) {}
        $activiteRecente    = array_slice(CommandeModel::getAll(['tri' => 'date_prestation_desc']), 0, 5);

        $today        = date('Y-m-d');
        $lundiSemaine = date('Y-m-d', strtotime('monday this week'));
        $commandesAujourdhui = array_filter($toutesCommandes, fn($c) => str_starts_with($c['date_commande'] ?? '', $today));
        $commandesSemaine    = array_filter($toutesCommandes, fn($c) => ($c['date_commande'] ?? '') >= $lundiSemaine);

        view('pages/employe/dashboard', compact(
            'commandesEnAttente', 'avisEnAttente',
            'commandesAujourdhui', 'commandesSemaine',
            'activiteRecente', 'alertesStock'
        ));
    }

    public function commandes(): void
    {
        $filters = [
            'statut'     => $_GET['statut']     ?? '',
            'q'          => $_GET['q']          ?? ($_GET['client'] ?? ''),
            'periode'    => $_GET['periode']    ?? '',
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin'   => $_GET['date_fin']   ?? '',
            'menu_id'    => $_GET['menu_id']    ?? '',
            'ville'      => $_GET['ville']      ?? '',
            'montant'    => $_GET['montant']    ?? '',
            'tri'        => $_GET['tri']        ?? 'date_prestation_desc',
        ];
        if ($filters['periode'] === '' && ($filters['date_debut'] !== '' || $filters['date_fin'] !== '')) {
            $filters['periode'] = 'custom';
        }

        $commandes   = CommandeModel::getAll($filters);
        $statuts     = OrderStatus::all();
        $menus       = \App\Models\MenuModel::getAll();
        $commandeIds = array_column($commandes, 'commande_id');

        $lignesByCommande    = CommandeModel::getLignesByCommandes($commandeIds);
        $documentsByCommande = \App\Models\FacturationModel::listByCommandeIds($commandeIds);
        $paiementsByCommande = \App\Models\PaiementModel::getSynthesesByCommandeIds($commandeIds);
        $paiementsHistorique = [];
        foreach ($commandeIds as $cid) {
            $paiementsHistorique[(int)$cid] = \App\Models\PaiementModel::getByCommande((int)$cid);
        }
        $modesPaiement = \App\Models\PaiementModel::getModePaiements();

        $statusFilters           = $filters;
        $statusFilters['statut'] = '';
        $statusCounts            = array_fill_keys($statuts, 0);
        foreach (CommandeModel::getAll($statusFilters) as $commande) {
            $statut = $commande['statut'] ?? '';
            if (array_key_exists($statut, $statusCounts)) {
                $statusCounts[$statut]++;
            }
        }

        view('pages/employe/commandes', compact(
            'commandes', 'filters', 'statuts', 'menus', 'statusCounts',
            'lignesByCommande', 'documentsByCommande', 'paiementsByCommande',
            'paiementsHistorique', 'modesPaiement'
        ));
    }

    public function calendrierJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $start = sanitize($_GET['start'] ?? '');
        $end   = sanitize($_GET['end']   ?? '');

        $filters = ['periode' => 'custom'];
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $start)) {
            $filters['date_debut'] = substr($start, 0, 10);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $end)) {
            $filters['date_fin'] = substr($end, 0, 10);
        }

        $commandes = CommandeModel::getAll($filters);

        $colorMap = [
            'en_attente'    => '#f59e0b',
            'accepte'       => '#10b981',
            'en_preparation'=> '#3b82f6',
            'livre'         => '#6366f1',
            'annule'        => '#ef4444',
            'termine'       => '#6b7280',
        ];

        $events = [];
        foreach ($commandes as $cmd) {
            $statut = $cmd['statut'] ?? 'en_attente';
            $client = trim(($cmd['prenom'] ?? '') . ' ' . ($cmd['nom'] ?? ''));
            $events[] = [
                'id'              => (int)$cmd['commande_id'],
                'title'           => $client . ' — ' . ($cmd['ville_livraison'] ?? ''),
                'start'           => $cmd['date_prestation'],
                'color'           => $colorMap[$statut] ?? '#8B1A2B',
                'extendedProps'   => [
                    'statut'          => $statut,
                    'numero'          => $cmd['numero_commande'] ?? '',
                    'menu'            => $cmd['menu_titre'] ?? '',
                    'heure'           => $cmd['heure_livraison'] ?? '',
                    'prix'            => (float)($cmd['prix_total'] ?? 0),
                    'commande_id'     => (int)$cmd['commande_id'],
                ],
            ];
        }

        echo json_encode($events, JSON_UNESCAPED_UNICODE);
        exit;
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

        if (!OrderStatus::isValid($statut)) {
            flash('error', 'Statut invalide.');
            redirect('/employe/commandes');
        }

        $ancienStatut = $commande['statut'] ?? null;

        if ($statut === OrderStatus::cancelled() && $action !== 'annuler') {
            flash('error', 'Une annulation doit passer par le formulaire dédié avec motif et confirmation.');
            redirect('/employe/commandes');
        }
        if ($action === 'annuler' && $statut !== OrderStatus::cancelled()) {
            flash('error', 'Requête d\'annulation invalide.');
            redirect('/employe/commandes');
        }
        if ($action === 'annuler' && $ancienStatut === OrderStatus::cancelled()) {
            flash('error', 'Cette commande est déjà annulée.');
            redirect('/employe/commandes');
        }

        if ($action === 'annuler') {
            $motif                  = $commentaire;
            $modeContact            = sanitize($_POST['mode_contact'] ?? '');
            $confirmationAnnulation = ($_POST['confirmation_annulation'] ?? '') === '1';
            if (!$motif || !$modeContact || !$confirmationAnnulation) {
                flash('error', 'Le motif, le mode de contact et la confirmation sont obligatoires pour une annulation.');
                redirect('/employe/commandes');
            }
            if (!in_array($modeContact, ['mail', 'gsm'], true)) {
                flash('error', 'Mode de contact invalide pour l\'annulation.');
                redirect('/employe/commandes');
            }
            CommandeModel::cancel($commandeId, $motif, $modeContact, $user['id']);
        } else {
            CommandeModel::updateStatut($commandeId, $statut, $commentaire ?: null, $user['id']);
        }

        $userCommande = UserModel::findById($commande['utilisateur_id']);
        if ($ancienStatut !== $statut && $statut === OrderStatus::completed() && $userCommande) {
            MailService::sendCommandeTerminee($userCommande['email'], $commandeId);
        }
        if ($ancienStatut !== $statut && $statut === OrderStatus::awaitingMaterial() && $userCommande) {
            MailService::sendMaterielRelance($userCommande['email'], $userCommande['prenom']);
        }

        flash('success', 'Statut mis à jour.');
        redirect('/employe/commandes');
    }

    public function changePasswordForm(): void
    {
        view('pages/employe/change_password');
    }

    public function changePassword(): void
    {
        verifyCsrf();

        $user     = currentUser();
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if ($password !== $confirm) {
            flash('error', 'Les mots de passe ne correspondent pas.');
            redirect('/employe/changer-mot-de-passe');
        }
        if (!validatePassword($password)) {
            flash('error', passwordPolicyMessage());
            redirect('/employe/changer-mot-de-passe');
        }

        UserModel::updatePassword($user['id'], hashPassword($password));
        UserModel::clearMustChangePassword($user['id']);
        $_SESSION['user']['must_change_password'] = false;

        flash('success', 'Mot de passe mis à jour. Bienvenue !');
        redirect('/employe');
    }
}
