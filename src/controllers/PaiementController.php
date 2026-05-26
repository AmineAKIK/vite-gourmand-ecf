<?php
// src/controllers/PaiementController.php

class PaiementController
{
    public function enregistrer(): void
    {
        verifyCsrf();
        $commandeId = (int)($_POST['commande_id'] ?? 0);

        try {
            if (!$commandeId) {
                throw new InvalidArgumentException('Commande introuvable.');
            }
            $commande = CommandeModel::getById($commandeId);
            if (!$commande) {
                throw new InvalidArgumentException('Commande introuvable.');
            }

            $paiementId = PaiementModel::create($_POST, (int)currentUser()['id']);

            // Si type = acompte et qu'un document_id est lié, mettre à jour les champs
            // montant_acompte_verse / solde_a_regler sur le document (pratique courante).
            $documentId = !empty($_POST['document_id']) ? (int)$_POST['document_id'] : null;
            if ($documentId && in_array($_POST['type_paiement'] ?? '', ['acompte', 'paiement_unique'], true)) {
                $synthese  = PaiementModel::getSyntheseByCommande($commandeId);
                $encaisse  = (float)($synthese['total_encaisse'] ?? 0);
                $prixTotal = (float)($commande['prix_total'] ?? 0);
                $solde     = max(0, round($prixTotal - $encaisse, 2));
                db()->execute(
                    "UPDATE document_facturation
                     SET montant_acompte_verse = ?, solde_a_regler = ?
                     WHERE document_id = ?",
                    [$encaisse, $solde, $documentId]
                );
            }

            flash('success', 'Paiement enregistré.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/employe/commandes#cmd-' . $commandeId);
    }

    public function supprimer(): void
    {
        verifyCsrf();
        $paiementId = (int)($_POST['paiement_id'] ?? 0);
        $commandeId = (int)($_POST['commande_id'] ?? 0);

        try {
            $paiement = PaiementModel::getById($paiementId);
            if (!$paiement) {
                throw new InvalidArgumentException('Paiement introuvable.');
            }
            PaiementModel::delete($paiementId);
            flash('success', 'Paiement supprimé.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/employe/commandes' . ($commandeId ? '#cmd-' . $commandeId : ''));
    }
}
