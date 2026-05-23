<?php
// src/controllers/AdminController.php

class AdminController {

    public function dashboard(): void {
        $toutesCommandes   = CommandeModel::getAll();
        $commandesEnAttente = CommandeModel::getAll(['statut' => 'en_attente']);
        $avisEnAttente     = AvisModel::getPending();
        $stats             = CommandeModel::getStatsByMenu();
        $mongoStats        = StatsService::getCommandesByMenu();

        // Métriques période
        $today      = date('Y-m-d');
        $lundiSemaine = date('Y-m-d', strtotime('monday this week'));
        $commandesAujourdhui = array_filter($toutesCommandes, fn($c) => str_starts_with($c['date_commande'] ?? '', $today));
        $commandesSemaine    = array_filter($toutesCommandes, fn($c) => ($c['date_commande'] ?? '') >= $lundiSemaine);
        $caSemaine = array_sum(array_map(
            fn($c) => in_array($c['statut'], ['accepte', 'en_preparation', 'en_cours_livraison', 'livre', 'en_attente_materiel', 'terminee'], true) ? (float)($c['prix_total'] ?? 0) : 0,
            $commandesSemaine
        ));

        // Fil d'activité : 5 dernières commandes
        $activiteRecente = array_slice($toutesCommandes, 0, 5);

        view('pages/admin/dashboard', compact(
            'commandesEnAttente', 'avisEnAttente',
            'commandesAujourdhui', 'commandesSemaine', 'caSemaine',
            'activiteRecente', 'stats', 'mongoStats'
        ));
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

    public function accueil(): void {
        $images = SiteImageModel::getAll();
        $config = SiteConfigModel::getAll();
        view('pages/admin/accueil', compact('images', 'config'));
    }

    public function images(): void {
        $images = SiteImageModel::getAll();
        view('pages/admin/images', compact('images'));
    }

    public function updateAccueil(): void {
        verifyCsrf();

        // Textes hero
        $sousTitre  = trim($_POST['hero_sous_titre'] ?? '');
        $paragraphe = trim($_POST['hero_paragraphe'] ?? '', " \t\r");

        if (mb_strlen($sousTitre) > 60) {
            flash('error', 'Le sous-titre ne peut pas dépasser 60 caractères.');
            redirect('/admin/accueil');
        }
        if (mb_strlen($paragraphe) > 200) {
            flash('error', 'Le paragraphe ne peut pas dépasser 200 caractères.');
            redirect('/admin/accueil');
        }

        SiteConfigModel::set('hero_sous_titre', $sousTitre);
        SiteConfigModel::set('hero_paragraphe', $paragraphe);

        // Images
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
                redirect('/admin/accueil');
            }
        }

        flash('success', 'Page d\'accueil mise à jour.');
        redirect('/admin/accueil');
    }

    public function updateImages(): void {
        verifyCsrf();

        foreach (['hero', 'preparation'] as $cle) {
            $file = $_FILES[$cle] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $url = MenuAdminService::uploadSiteImage($file, 'site/' . $cle);
            if (!$url) {
                flash('error', 'Erreur lors de l\'upload de l\'image "' . $cle . '".');
                redirect('/admin/images');
            }

            SiteImageModel::set($cle, $url);
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
            'hero_sous_titre' => ['type' => 'string', 'max' => 60],
            'hero_paragraphe' => ['type' => 'string', 'max' => 200],
            'livraison_base'  => ['type' => 'decimal'],
            'livraison_km'    => ['type' => 'decimal'],
            'reduction_seuil' => ['type' => 'decimal'],
            'reduction_taux'  => ['type' => 'int', 'min' => 0, 'max' => 100],
        ];

        foreach ($fields as $cle => $rules) {
            $raw = trim($_POST[$cle] ?? '');
            $label = match ($cle) {
                'hero_sous_titre' => 'Le sous-titre',
                'hero_paragraphe' => 'Le paragraphe',
                'livraison_base' => 'Les frais fixes de livraison',
                'livraison_km' => 'Le tarif au km',
                'reduction_seuil' => 'Le seuil de réduction',
                'reduction_taux' => 'Le taux de réduction',
                default => 'La valeur',
            };

            if ($rules['type'] === 'string') {
                if (mb_strlen($raw) > $rules['max']) {
                    flash('error', $label . ' ne peut pas dépasser ' . $rules['max'] . ' caractères.');
                    redirect('/admin/parametres');
                }
                SiteConfigModel::set($cle, $raw);
                continue;
            }

            if ($rules['type'] === 'decimal') {
                if (!is_numeric($raw) || (float)$raw < 0) {
                    flash('error', $label . ' est invalide.');
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
