<?php

namespace App\Controllers\Admin;

use App\Config\PlanConfig;
use App\Models\UserModel;
use App\Services\MailService;

class EmployeAdminController
{
    public function index(): void
    {
        $employes = UserModel::getAllEmployes();
        view('pages/admin/employes', compact('employes'));
    }

    public function create(): void
    {
        verifyCsrf();

        $email    = sanitize($_POST['email']    ?? '');
        $prenom   = sanitize($_POST['prenom']   ?? '');
        $nom      = sanitize($_POST['nom']      ?? '');
        $password = $_POST['password']          ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Email invalide.');
            redirect('/admin/employes');
        }
        if (!$prenom || !$nom) {
            flash('error', 'Prénom et nom obligatoires.');
            redirect('/admin/employes');
        }
        if (!validatePassword($password)) {
            flash('error', passwordPolicyMessage());
            redirect('/admin/employes');
        }
        if (UserModel::findByEmail($email)) {
            flash('error', 'Email déjà utilisé.');
            redirect('/admin/employes');
        }

        try {
            PlanConfig::checkEmployesQuota();
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/employes');
        }

        UserModel::createEmploye($email, hashPassword($password), $prenom, $nom);
        MailService::sendEmployeCreation($email);

        flash('success', 'Compte employé créé. Le mot de passe doit être communiqué manuellement.');
        redirect('/admin/employes');
    }

    public function disable(): void
    {
        verifyCsrf();

        $id    = (int)($_POST['employe_id'] ?? 0);
        $actif = (int)($_POST['actif']      ?? 0);
        UserModel::setActif($id, (bool)$actif);
        flash('success', 'Compte ' . ($actif ? 'réactivé' : 'désactivé') . '.');
        redirect('/admin/employes');
    }

    public function delete(): void
    {
        verifyCsrf();

        $id      = (int)($_POST['employe_id'] ?? 0);
        $employe = UserModel::findById($id);
        if (!$employe || $employe['role_libelle'] !== ROLE_EMPLOYE) {
            flash('error', 'Employé introuvable.');
            redirect('/admin/employes');
        }

        UserModel::deleteEmploye($id);
        flash('success', 'Compte employé supprimé définitivement.');
        redirect('/admin/employes');
    }
}
