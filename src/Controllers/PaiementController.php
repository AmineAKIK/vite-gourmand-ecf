<?php

namespace App\Controllers;

use App\Config\Database;
use App\Domain\Money;
use App\Models\CommandeModel;
use App\Models\PaiementModel;
use InvalidArgumentException;
use Throwable;

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

            $paymentData = $_POST;
            $paymentData['montant_cents'] = Money::fromDecimal((string)($_POST['montant'] ?? ''));
            unset($paymentData['montant']);
            PaiementModel::create($paymentData, (int)currentUser()['id']);

            $documentId = !empty($_POST['document_id']) ? (int)$_POST['document_id'] : null;
            if ($documentId && in_array($_POST['type_paiement'] ?? '', ['acompte', 'paiement_unique'], true)) {
                $synthese = PaiementModel::getSyntheseByCommande($commandeId);
                $encaisseCents = (int)($synthese['total_encaisse_cents'] ?? 0);
                $prixTotalCents = (int)($commande['prix_total_cents'] ?? 0);
                $soldeCents = max(0, $prixTotalCents - $encaisseCents);
                $db = Database::getConnection();
                $db->prepare("UPDATE document_facturation SET montant_acompte_verse = ?, solde_a_regler = ? WHERE document_id = ?")
                   ->execute([
                       Money::toDecimalString($encaisseCents),
                       Money::toDecimalString($soldeCents),
                       $documentId,
                   ]);
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
            PaiementModel::delete($paiementId, (int)currentUser()['id']);
            flash('success', 'Paiement contre-passé. L’écriture d’origine est conservée.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/employe/commandes' . ($commandeId ? '#cmd-' . $commandeId : ''));
    }
}
