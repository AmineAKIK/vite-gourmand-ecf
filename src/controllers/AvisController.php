<?php
// src/controllers/AvisController.php
class AvisController
{
    public function create(): void
    {
        requireAuth();
        verifyCsrf();

        $user        = currentUser();
        $commandeId  = (int)($_POST['commande_id'] ?? 0);
        $note        = (int)($_POST['note']         ?? 0);
        $commentaire = sanitize($_POST['commentaire'] ?? '');

        if ($note < 1 || $note > 5) {
            flash('error', 'Note invalide.');
            redirect('/mon-compte');
        }

        $commande = CommandeModel::getById($commandeId);
        if (!$commande || $commande['utilisateur_id'] != $user['id'] || !commandeCanReview($commande['statut'] ?? null)) {
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
