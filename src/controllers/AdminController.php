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

    public function parametres(): void {
        $config = SiteConfigModel::getAll();
        view('pages/admin/parametres', compact('config'));
    }

    public function updateParametres(): void {
        verifyCsrf();

        $fields = [
            'hero_sous_titre' => ['type' => 'string', 'max' => 120],
            'livraison_base'  => ['type' => 'decimal'],
            'livraison_km'    => ['type' => 'decimal'],
            'reduction_seuil' => ['type' => 'decimal'],
            'reduction_taux'  => ['type' => 'int', 'min' => 0, 'max' => 100],
        ];

        foreach ($fields as $cle => $rules) {
            $raw = trim($_POST[$cle] ?? '');

            if ($rules['type'] === 'string') {
                if (strlen($raw) > $rules['max']) {
                    flash('error', 'Le sous-titre ne peut pas dépasser ' . $rules['max'] . ' caractères.');
                    redirect('/admin/parametres');
                }
                SiteConfigModel::set($cle, $raw);
                continue;
            }

            if ($rules['type'] === 'decimal') {
                if (!is_numeric($raw) || (float)$raw < 0) {
                    flash('error', 'Valeur invalide pour "' . $cle . '".');
                    redirect('/admin/parametres');
                }
                SiteConfigModel::set($cle, number_format((float)$raw, 2, '.', ''));
                continue;
            }

            if ($rules['type'] === 'int') {
                $val = (int)$raw;
                if ($val < ($rules['min'] ?? 0) || $val > ($rules['max'] ?? PHP_INT_MAX)) {
                    flash('error', 'Le taux de réduction doit être entre 0 et 100.');
                    redirect('/admin/parametres');
                }
                SiteConfigModel::set($cle, (string)$val);
            }
        }

        flash('success', 'Paramètres mis à jour.');
        redirect('/admin/parametres');
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
