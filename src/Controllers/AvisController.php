<?php

namespace App\Controllers;

use App\Domain\OrderStatus;
use App\Models\AvisModel;
use App\Models\CommandeModel;

class AvisController
{
    public function create(): void
    {
        requireAuth();
        verifyCsrf();

        $user        = currentUser();
        $commandeId  = (int)($_POST['commande_id'] ?? 0);
        $note        = (int)($_POST['note']         ?? 0);
        $commentaire = trim($_POST['commentaire'] ?? '');

        if ($note < 1 || $note > 5) {
            redirect('/mon-compte?open_modal=avis_' . $commandeId . '&modal_error=' . urlencode('Veuillez sélectionner une note entre 1 et 5.'));
        }

        if (mb_strlen($commentaire) > 300) {
            flash('error', 'Le commentaire ne peut pas dépasser 300 caractères.');
            redirect('/mon-compte');
        }

        $commande = CommandeModel::getById($commandeId);
        if (!$commande || $commande['utilisateur_id'] != $user['id'] || !OrderStatus::clientCanReview($commande['statut'] ?? null)) {
            flash('error', 'Impossible de laisser un avis.');
            redirect('/mon-compte');
        }

        if (AvisModel::existsForCommande($commandeId)) {
            flash('error', 'Vous avez déjà laissé un avis pour cette commande.');
            redirect('/mon-compte');
        }

        AvisModel::create($commandeId, $user['id'], $note, $commentaire);

        flash('success', 'Merci pour votre avis ! Il sera affiché après validation.');
        redirect('/mon-compte');
    }
}
