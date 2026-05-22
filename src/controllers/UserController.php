<?php
// src/controllers/UserController.php

require_once __DIR__ . '/../models/CommandeModel.php';

class UserController {

    public function dashboard(): void {
        $user      = currentUser();
        $commandes = CommandeModel::getByUser($user['id']);
        $userFull  = \UserModel::findById($user['id']);
        view('pages/user/dashboard', compact('commandes', 'userFull'));
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
}
