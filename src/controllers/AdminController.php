<?php
// src/controllers/AdminController.php

class AdminController {

    public function dashboard(): void {
        $commandes = CommandeModel::getAll();
        $stats     = CommandeModel::getStatsByMenu();
        $mongoStats = StatsService::getCommandesByMenu();
        view('pages/admin/dashboard', compact('commandes', 'stats', 'mongoStats'));
    }

    public function employes(): void {
        $employes = \UserModel::getAllEmployes();
        view('pages/admin/employes', compact('employes'));
    }

    public function createEmploye(): void {
        verifyCsrf();
        $email    = sanitize($_POST['email'] ?? '');
        $prenom   = sanitize($_POST['prenom'] ?? '');
        $nom      = sanitize($_POST['nom'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Email invalide.'); redirect('/admin/employes');
        }
        if (!$prenom || !$nom) {
            flash('error', 'Prénom et nom obligatoires.'); redirect('/admin/employes');
        }
        if (!validatePassword($password)) {
            flash('error', passwordPolicyMessage());
            redirect('/admin/employes');
        }
        if (\UserModel::findByEmail($email)) {
            flash('error', 'Email déjà utilisé.'); redirect('/admin/employes');
        }

        $hash = hashPassword($password);
        \UserModel::createEmploye($email, $hash, $prenom, $nom);
        MailService::sendEmployeCreation($email);

        flash('success', 'Compte employé créé. Le mot de passe doit être communiqué manuellement.');
        redirect('/admin/employes');
    }

    public function disableEmploye(): void {
        verifyCsrf();
        $id    = (int)($_POST['employe_id'] ?? 0);
        $actif = (int)($_POST['actif'] ?? 0);
        \UserModel::setActif($id, (bool)$actif);
        flash('success', 'Compte ' . ($actif ? 'réactivé' : 'désactivé') . '.');
        redirect('/admin/employes');
    }

    public function deleteEmploye(): void {
        verifyCsrf();
        $id = (int)($_POST['employe_id'] ?? 0);
        $employe = \UserModel::findById($id);
        if (!$employe || $employe['role_libelle'] !== ROLE_EMPLOYE) {
            flash('error', 'Employé introuvable.');
            redirect('/admin/employes');
        }
        \UserModel::deleteEmploye($id);
        flash('success', 'Compte employé supprimé définitivement.');
        redirect('/admin/employes');
    }

    public function images(): void {
        $images = SiteImageModel::getAll();
        view('pages/admin/images', compact('images'));
    }

    public function updateImages(): void {
        verifyCsrf();

        $cles = ['hero', 'preparation'];
        foreach ($cles as $cle) {
            $file = $_FILES[$cle] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $url = MenuAdminService::uploadSiteImage($file, 'site/' . $cle);
            if ($url) {
                SiteImageModel::set($cle, $url);
            } else {
                flash('error', 'Erreur lors de l\'upload de l\'image "' . $cle . '".');
                redirect('/admin/images');
            }
        }

        flash('success', 'Images du site mises à jour.');
        redirect('/admin/images');
    }

    public function stats(): void {
        $menuFilter = (int)($_GET['menu_id'] ?? 0);
        $dateDebut  = sanitize($_GET['date_debut'] ?? '');
        $dateFin    = sanitize($_GET['date_fin'] ?? '');
        $caStats = CommandeModel::getCaStatsByMenu($menuFilter, $dateDebut, $dateFin);
        $mongoStats = StatsService::getCommandesByMenu();
        $menus = \MenuModel::getAll();
        view('pages/admin/stats', compact(
            'caStats', 'menus',
            'menuFilter', 'dateDebut', 'dateFin', 'mongoStats'
        ));
    }

}
