<?php

namespace App\Controllers\Workspace;

use App\Models\AvisModel;

class AvisAdminController
{
    public function index(): void
    {
        $filtre          = in_array($_GET['filtre'] ?? '', ['en_attente', 'valide', 'refuse']) ? $_GET['filtre'] : 'en_attente';
        $avis            = AvisModel::getAll($filtre);
        $pending         = AvisModel::getPending();
        $doublonsAccueil = AvisModel::getHomepageDuplicateClients();

        view('pages/employe/avis', compact('avis', 'filtre', 'pending', 'doublonsAccueil'));
    }

    public function valider(): void
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

    public function toggleAccueil(): void
    {
        verifyCsrf();

        $avisId   = (int)($_POST['avis_id'] ?? 0);
        $filtre   = sanitize($_POST['filtre'] ?? 'valide');
        $featured = ($_POST['afficher_accueil'] ?? '') === '1';

        if (!$avisId || !AvisModel::setHomepageFeatured($avisId, $featured)) {
            flash('error', 'Seul un avis validé peut être affiché sur l\'accueil.');
            redirect('/employe/avis?filtre=' . urlencode($filtre));
        }

        flash('success', $featured ? 'Avis ajouté à l\'accueil.' : 'Avis retiré de l\'accueil.');
        redirect('/employe/avis?filtre=' . urlencode($filtre));
    }

    public function supprimer(): void
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
}
