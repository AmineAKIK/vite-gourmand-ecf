<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\PlanConfig;
use App\Config\SiteConfig;
use App\Geo\Exception\DeliveryGeoNotConfiguredException;
use App\Geo\Exception\DeliveryOutOfRangeException;
use App\Models\CommandeModel;
use App\Models\PaymentAttemptModel;
use App\Models\UserModel;
use App\Services\CommandeService;
use App\Services\MailService;
use App\Services\OrderAdmissionService;
use App\Services\OrderTransitionService;
use App\Services\PricingService;

class CommandeController {

    public function calculLivraison(): void {
        header('Content-Type: application/json; charset=utf-8');
        $adresse = sanitize($_GET['adresse'] ?? '');
        $ville = sanitize($_GET['ville'] ?? '');
        $codePostal = sanitize($_GET['code_postal'] ?? '');
        if (!$adresse || !$ville || !$codePostal) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Adresse, ville et code postal sont requis.']);
            return;
        }

        try {
            $prix = \App\Geo\DeliveryResolver::computeDeliveryPrice($adresse, $ville, $codePostal);
        } catch (DeliveryGeoNotConfiguredException $e) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'message' => 'Le service de livraison n\'est pas encore configuré. Contactez le traiteur.']);
            return;
        } catch (DeliveryOutOfRangeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'hors_rayon' => true]);
            return;
        }

        if ($prix === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Adresse non reconnue ou incohérente avec le code postal.']);
            return;
        }

        $adresseResolue = resolveAdresseLivraison($adresse, $ville, $codePostal);
        $distance = $adresseResolue
            ? distanceKmDepuisCoordonnees((float)$adresseResolue['lat'], (float)$adresseResolue['lng'])
            : null;

        echo json_encode([
            'ok'       => true,
            'distance' => $distance,
            'prix'     => $prix,
            'adresse'  => $adresseResolue['label'] ?? null,
        ]);
    }

    public function calculDisponibilite(): void {
        header('Content-Type: application/json; charset=utf-8');
        $date = sanitize($_GET['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Date invalide.']);
            return;
        }

        try {
            $count = OrderAdmissionService::countCommittedAndReservedForDay(
                Database::getConnection(),
                $date,
            );
        } catch (\Throwable $e) {
            error_log('[admission] calcul disponibilité impossible: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['ok' => false, 'message' => 'Disponibilité temporairement indisponible.']);
            return;
        }

        $max = SiteConfig::commandesMaxParJour();
        echo json_encode([
            'ok'      => true,
            'count'   => $count,
            'max'     => $max,
            'complet' => $max > 0 && $count >= $max,
        ]);
    }

    public function create(): void {
        requireAuth();
        verifyCsrf();
        $user   = currentUser();
        $panier = $_SESSION['panier'] ?? [];

        if (empty($panier)) {
            flash('error', 'Votre panier est vide.');
            redirect('/panier');
        }

        // Valider les champs de livraison (date, heure, format)
        try {
            CommandeService::validateLivraisonFields($_POST);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/panier');
        }

        // Détecter les changements de prix depuis la mise en panier
        $changes = PricingService::detectPrixChanges($panier);
        if (!empty($changes)) {
            $titres = implode(', ', array_column($changes, 'titre'));
            flash('error', 'Le prix du menu "' . $titres . '" a changé depuis votre mise en panier. Votre panier a été mis à jour — veuillez vérifier les nouveaux montants.');
            // Mettre à jour les prix dans la session avant de rediriger
            foreach ($_SESSION['panier'] as &$item) {
                foreach ($changes as $change) {
                    if ((int)$item['menu_id'] === $change['menu_id']) {
                        $item['prix_par_personne'] = $change['prix_actuel'];
                    }
                }
            }
            unset($item);
            redirect('/panier');
        }

        $adresse    = sanitize($_POST['adresse_livraison']     ?? '');
        $ville      = sanitize($_POST['ville_livraison']       ?? '');
        $codePostal = sanitize($_POST['code_postal_livraison'] ?? '');

        // Calcul complet via PricingService (réduction sur total global, snapshots)
        try {
            $pricing = PricingService::computeOrderTotal($panier, $adresse, $ville, $codePostal);
        } catch (DeliveryOutOfRangeException $e) {
            flash('error', $e->getMessage());
            redirect('/panier');
        } catch (DeliveryGeoNotConfiguredException) {
            flash('error', 'Le service de livraison n\'est pas encore configuré. Contactez le traiteur.');
            redirect('/panier');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/panier');
        }

        $modePaiement = sanitize($_POST['mode_paiement'] ?? 'virement');

        // Validate mode_paiement exists and is active
        $modeActif = db()->fetchOne(
            "SELECT code FROM mode_paiement WHERE code = ? AND actif = 1",
            [$modePaiement]
        );
        if (!$modeActif) {
            flash('error', 'Mode de paiement invalide.');
            redirect('/panier');
        }

        $numeroCommande = generateNumeroCommande();

        $instructions = trim(sanitize($_POST['instructions'] ?? ''));

        $commandeData = [
            'numero_commande'       => $numeroCommande,
            'utilisateur_id'        => $user['id'],
            'date_prestation'       => sanitize($_POST['date_prestation']  ?? ''),
            'heure_livraison'       => sanitize($_POST['heure_livraison']  ?? ''),
            'adresse_livraison'     => $adresse,
            'ville_livraison'       => $ville,
            'code_postal_livraison' => $codePostal,
            'prix_total'            => $pricing['total_ttc'],
            'prix_livraison'        => $pricing['prix_livraison'],
            'instructions'          => $instructions ?: null,
        ];

        // CB en ligne : draft + tentative + réservation admission sont persistés
        // dans une transaction unique avant toute redirection externe.
        if ($modePaiement === 'cb_online') {
            try {
                $draft = PaymentAttemptModel::createDraftWithAttempt(
                    $commandeData,
                    $pricing,
                    $panier,
                    (int) $user['id']
                );
            } catch (\RuntimeException $e) {
                error_log('[payment] création draft refusée ref=' . $numeroCommande . ': ' . $e->getMessage());
                flash('error', $e->getMessage());
                redirect('/panier');
            } catch (\Throwable $e) {
                error_log('[payment] création draft impossible ref=' . $numeroCommande . ': ' . $e->getMessage());
                flash('error', 'Impossible de préparer le paiement en ligne. Veuillez réessayer.');
                redirect('/panier');
            }

            $_SESSION['stripe_draft_id'] = $draft['draft_id'];
            $_SESSION['stripe_attempt_id'] = $draft['attempt_id'];
            unset($_SESSION['stripe_pending']);
            redirect('/stripe/checkout');
        }

        $admissionDb = Database::getConnection();
        $admissionDb->beginTransaction();
        try {
            OrderAdmissionService::reserve(
                $admissionDb,
                $numeroCommande,
                (string) $commandeData['date_prestation'],
                SiteConfig::commandesMaxParJour(),
                PlanConfig::maxCommandesMois(),
                date('Y-m-d H:i:s', time() + 600),
            );
            $admissionDb->commit();
        } catch (\RuntimeException $e) {
            if ($admissionDb->inTransaction()) {
                $admissionDb->rollBack();
            }
            flash('error', $e->getMessage());
            redirect('/panier');
        } catch (\Throwable $e) {
            if ($admissionDb->inTransaction()) {
                $admissionDb->rollBack();
            }
            error_log('[admission] réservation impossible ref=' . $numeroCommande . ': ' . $e->getMessage());
            flash('error', 'Impossible de réserver ce créneau. Veuillez réessayer.');
            redirect('/panier');
        }

        try {
            $commandeId = CommandeModel::create($commandeData, $pricing['lignes']);
        } catch (\Throwable $e) {
            try {
                $releaseDb = Database::getConnection();
                $releaseDb->beginTransaction();
                OrderAdmissionService::release($releaseDb, $numeroCommande);
                $releaseDb->commit();
            } catch (\Throwable $releaseError) {
                if (isset($releaseDb) && $releaseDb->inTransaction()) {
                    $releaseDb->rollBack();
                }
                error_log('[admission] libération impossible ref=' . $numeroCommande . ': ' . $releaseError->getMessage());
            }
            flash('error', 'Un ou plusieurs menus ne sont plus disponibles.');
            redirect('/panier');
        }

        try {
            $consumeDb = Database::getConnection();
            $consumeDb->beginTransaction();
            OrderAdmissionService::consume(
                $consumeDb,
                $numeroCommande,
                $commandeId,
                (string) $commandeData['date_prestation'],
            );
            $consumeDb->commit();
        } catch (\Throwable $e) {
            if (isset($consumeDb) && $consumeDb->inTransaction()) {
                $consumeDb->rollBack();
            }
            // La commande existe déjà et reste comptée. Le permit de secours expire
            // automatiquement ; ne jamais transformer cela en échec client.
            error_log('[admission] consommation impossible ref=' . $numeroCommande . ': ' . $e->getMessage());
        }

        // La consommation de stock reste non bloquante tant que le redesign métier
        // n'est pas en place, mais une erreur doit être visible en production.
        try {
            \App\Models\StockModel::consommerPourCommande($commandeId, (int)$user['id']);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[stock] consommation impossible pour commande_id=%d: %s',
                $commandeId,
                $e->getMessage()
            ));
        }

        $userFull = UserModel::findById($user['id']);
        MailService::sendCommandeConfirmation($userFull['email'], $commandeData, $panier);

        $_SESSION['panier'] = [];

        flash('success', 'Commande #' . $numeroCommande . ' passée avec succès !');
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

        // Re-calculer le total avec la nouvelle adresse via PricingService
        // (les lignes menus restent inchangées, seule la livraison peut varier)
        $lignes = CommandeModel::getLignes((int)$commande['commande_id']);

        try {
            CommandeService::validateLivraisonFields($_POST);
        } catch (\InvalidArgumentException $e) {
            redirect('/mon-compte?open_modal=modif_' . (int)$commande['commande_id'] . '&modal_error=' . urlencode($e->getMessage()));
        }

        $adresse    = sanitize($_POST['adresse_livraison']     ?? '');
        $ville      = sanitize($_POST['ville_livraison']       ?? '');
        $codePostal = sanitize($_POST['code_postal_livraison'] ?? '');

        // Reconstruire les items panier depuis les lignes DB pour PricingService
        $panierItemsFromLignes = array_map(fn($l) => [
            'menu_id'          => $l['menu_id'],
            'nombre_personne'  => $l['nombre_personne'],
            'prix_par_personne'=> $l['prix_par_personne_snapshot'] > 0
                                  ? $l['prix_par_personne_snapshot']
                                  : $l['prix_par_personne'],   // fallback DB si snapshot absent
        ], $lignes);

        try {
            $pricing = PricingService::computeOrderTotal($panierItemsFromLignes, $adresse, $ville, $codePostal);
        } catch (DeliveryOutOfRangeException $e) {
            redirect('/mon-compte?open_modal=modif_' . (int)$commande['commande_id'] . '&modal_error=' . urlencode($e->getMessage()));
        } catch (DeliveryGeoNotConfiguredException) {
            redirect('/mon-compte?open_modal=modif_' . (int)$commande['commande_id'] . '&modal_error=' . urlencode('Le service de livraison n\'est pas encore configuré.'));
        } catch (\InvalidArgumentException $e) {
            redirect('/mon-compte?open_modal=modif_' . (int)$commande['commande_id'] . '&modal_error=' . urlencode($e->getMessage()));
        }

        $instructionsUpdate = trim(sanitize($_POST['instructions'] ?? ''));

        $payload = [
            'date_prestation'       => sanitize($_POST['date_prestation']  ?? ''),
            'heure_livraison'       => sanitize($_POST['heure_livraison']  ?? ''),
            'adresse_livraison'     => $adresse,
            'ville_livraison'       => $ville,
            'code_postal_livraison' => $codePostal,
            'prix_total'            => $pricing['total_ttc'],
            'prix_livraison'        => $pricing['prix_livraison'],
            'instructions'          => $instructionsUpdate ?: null,
        ];

        CommandeModel::updateDetails((int)$commande['commande_id'], $payload);

        $msg = 'Commande modifiée. Nouveau total : ' . formatPrice($payload['prix_total']);
        if (abs($payload['prix_total'] - (float)$commande['prix_total']) > 0.01) {
            $msg .= ' (ancien total : ' . formatPrice($commande['prix_total']) . ')';
        }
        flash('success', $msg);
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

        try {
            $transition = OrderTransitionService::cancel(
                (int) $commande['commande_id'],
                'Annulation demandée par le client',
                'client',
                (int) $user['id'],
            );
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/mon-compte');
        }

        flash('success', $transition['changed'] ? 'Commande annulée.' : 'Commande déjà annulée.');
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
