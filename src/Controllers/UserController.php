<?php

namespace App\Controllers;

use App\Models\AvisModel;
use App\Models\CommandeModel;
use App\Models\UserModel;

class UserController {

    public function dashboard(): void {
        $user      = currentUser();
        $commandes = CommandeModel::getByUser($user['id']);
        $userFull  = UserModel::findById($user['id']);

        $avisByCommande = AvisModel::getByCommandes(array_column($commandes, 'commande_id'));

        view('pages/user/dashboard', compact('commandes', 'userFull', 'avisByCommande'));
    }

    public function update(): void {
        verifyCsrf();
        $user = currentUser();
        $data = [
            'prenom'      => sanitize($_POST['prenom'] ?? ''),
            'nom'         => sanitize($_POST['nom'] ?? ''),
            'telephone'   => sanitize($_POST['telephone'] ?? ''),
            'adresse'     => sanitize($_POST['adresse'] ?? ''),
            'ville'       => sanitize($_POST['ville'] ?? ''),
            'code_postal' => sanitize($_POST['code_postal'] ?? ''),
        ];

        if (!$data['prenom'] || !$data['nom']) {
            flash('error', 'Prénom et nom obligatoires.');
            redirect('/mon-compte');
        }

        \UserModel::update($user['id'], $data);

        // Mettre à jour la session
        $_SESSION['user']['prenom'] = $data['prenom'];
        $_SESSION['user']['nom']    = $data['nom'];

        flash('success', 'Informations mises à jour.');
        redirect('/mon-compte');
    }

    public function exportCommandes(): void {
        requireAuth();
        $user      = currentUser();
        $commandes = CommandeModel::getByUser($user['id']);

        $filename = 'mes-commandes-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
        fputcsv($out, ['N° commande', 'Date commande', 'Date prestation', 'Menu(s)', 'Adresse livraison', 'Total TTC', 'Statut'], ';');
        foreach ($commandes as $cmd) {
            fputcsv($out, [
                $cmd['numero_commande'] ?? '',
                $cmd['date_commande']   ?? '',
                $cmd['date_prestation'] ?? '',
                $cmd['menu_titre']      ?? '',
                trim(($cmd['adresse_livraison'] ?? '') . ' ' . ($cmd['code_postal_livraison'] ?? '') . ' ' . ($cmd['ville_livraison'] ?? '')),
                number_format((float)($cmd['prix_total'] ?? 0), 2, ',', ''),
                $cmd['statut'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function deleteAccount(): void {
        requireAuth();
        verifyCsrf();
        $user = currentUser();

        if (!hasRole(ROLE_USER)) {
            flash('error', 'Seuls les clients peuvent supprimer leur compte.');
            redirect('/mon-compte');
        }

        \UserModel::delete($user['id']);

        session_destroy();
        redirect('/');
    }
}
