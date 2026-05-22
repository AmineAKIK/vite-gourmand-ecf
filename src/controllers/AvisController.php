<?php
// src/controllers/AvisController.php
require_once __DIR__ . '/../models/CommandeModel.php';

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
        if (!$commande || $commande['utilisateur_id'] != $user['id'] || $commande['statut'] !== 'terminee') {
            flash('error', 'Impossible de laisser un avis.');
            redirect('/mon-compte');
        }

        $db = Database::getConnection();

        /* Vérifie qu'un avis n'existe pas déjà pour cette commande */
        $stmt = $db->prepare("SELECT avis_id FROM avis WHERE commande_id = ?");
        $stmt->execute([$commandeId]);
        if ($stmt->fetch()) {
            flash('error', 'Vous avez déjà laissé un avis pour cette commande.');
            redirect('/mon-compte');
        }

        $db->prepare("INSERT INTO avis (commande_id, utilisateur_id, note, description) VALUES (?, ?, ?, ?)")
           ->execute([$commandeId, $user['id'], $note, $commentaire]);

        flash('success', 'Merci pour votre avis ! Il sera affiché après validation.');
        redirect('/mon-compte');
    }
}
