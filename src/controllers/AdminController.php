<?php
// src/controllers/AdminController.php

class AdminController {

    public function dashboard(): void {
        $toutesCommandes   = CommandeModel::getAll();
        $commandesEnAttente = CommandeModel::getAll(['statut' => 'en_attente']);
        $avisEnAttente     = AvisModel::getPending();
        $menusActifs       = MenuModel::getAll();
        $statsParMenu      = StatsService::getCaParMenu();

        // Métriques période
        $today      = date('Y-m-d');
        $lundiSemaine = date('Y-m-d', strtotime('monday this week'));
        $commandesAujourdhui = array_filter($toutesCommandes, fn($c) => str_starts_with($c['date_commande'] ?? '', $today));
        $commandesSemaine    = array_filter($toutesCommandes, fn($c) => ($c['date_commande'] ?? '') >= $lundiSemaine);
        $caSemaine = array_sum(array_map(
            fn($c) => commandeCountsTowardRevenue($c['statut'] ?? null) ? (float)($c['prix_total'] ?? 0) : 0,
            array_filter(
                $toutesCommandes,
                fn($c) => ($c['date_acceptation'] ?? $c['date_commande'] ?? '') >= $lundiSemaine
            )
        ));

        // Fil d'activité : prestations les plus récentes affichées en premier.
        $activiteRecente = array_slice(CommandeModel::getAll(['tri' => 'date_prestation_desc']), 0, 5);

        view('pages/admin/dashboard', compact(
            'commandesEnAttente', 'avisEnAttente',
            'commandesAujourdhui', 'commandesSemaine', 'caSemaine',
            'activiteRecente', 'menusActifs', 'statsParMenu'
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

    public function updateParametres(): void {
        verifyCsrf();
        $section = $_POST['_section'] ?? 'tarification';

        // Field definitions keyed by section
        $allFields = [
            'identite' => [
                'site_nom'                        => ['type' => 'string',  'max' => 100],
                'site_slogan'                     => ['type' => 'string',  'max' => 100],
                'site_domaine'                    => ['type' => 'string',  'max' => 100],
                'site_email'                      => ['type' => 'email'],
                'site_telephone'                  => ['type' => 'string',  'max' => 30],
                'site_adresse'                    => ['type' => 'string',  'max' => 150],
                'site_code_postal'                => ['type' => 'cp'],
                'site_ville'                      => ['type' => 'string',  'max' => 80],
                'couleur_principale'              => ['type' => 'couleur'],
                'couleur_secondaire'              => ['type' => 'couleur'],
                'couleur_fond'                    => ['type' => 'couleur'],
                'livraison_lat'                   => ['type' => 'coord'],
                'livraison_lng'                   => ['type' => 'coord'],
                'livraison_codes_postaux_gratuits'=> ['type' => 'string',  'max' => 500],
            ],
            'entreprise' => [
                'entreprise_nom'             => ['type' => 'string',  'max' => 100],
                'entreprise_siret'           => ['type' => 'siret'],
                'entreprise_forme_juridique' => ['type' => 'string',  'max' => 60],
                'entreprise_adresse'         => ['type' => 'string',  'max' => 150],
                'entreprise_code_postal'     => ['type' => 'cp'],
                'entreprise_ville'           => ['type' => 'string',  'max' => 80],
                'entreprise_telephone'       => ['type' => 'string',  'max' => 20],
                'entreprise_email'           => ['type' => 'email'],
                'entreprise_tva_intracom'    => ['type' => 'string',  'max' => 20],
                'banque_iban'                => ['type' => 'string',  'max' => 34],
                'banque_bic'                 => ['type' => 'string',  'max' => 11],
                'banque_nom_banque'          => ['type' => 'string',  'max' => 80],
            ],
            'fiscal' => [
                'regime_tva'                 => ['type' => 'enum', 'values' => ['assujetti', 'non_assujetti']],
                'mention_facture'            => ['type' => 'string',  'max' => 500],
                'mention_ticket'             => ['type' => 'string',  'max' => 500],
                'mention_acompte'            => ['type' => 'string',  'max' => 500],
            ],
            'paiement' => [
                'acompte_taux_defaut'        => ['type' => 'int',     'min' => 0,   'max' => 100],
                'delai_paiement_jours'       => ['type' => 'int',     'min' => 0,   'max' => 365],
                'penalites_retard_taux'      => ['type' => 'decimal', 'min' => 0],
                'indemnite_recouvrement'     => ['type' => 'decimal', 'min' => 0],
            ],
            'tarification' => [
                'hero_sous_titre'            => ['type' => 'string',  'max' => 60],
                'hero_paragraphe'            => ['type' => 'string',  'max' => 200],
                'livraison_base'             => ['type' => 'decimal', 'min' => 0],
                'livraison_km'               => ['type' => 'decimal', 'min' => 0],
                'reduction_seuil'            => ['type' => 'decimal', 'min' => 0],
                'reduction_taux'             => ['type' => 'int',     'min' => 0,   'max' => 100],
            ],
        ];

        $fields = $allFields[$section] ?? $allFields['tarification'];

        foreach ($fields as $cle => $rules) {
            $raw = trim($_POST[$cle] ?? '');

            switch ($rules['type']) {
                case 'string':
                    if (mb_strlen($raw) > ($rules['max'] ?? 255)) {
                        flash('error', "Le champ '$cle' dépasse la longueur maximale autorisée.");
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, $raw);
                    break;

                case 'email':
                    if ($raw !== '' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                        flash('error', "L'adresse email '$raw' est invalide.");
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, $raw);
                    break;

                case 'siret':
                    $digits = preg_replace('/\s/', '', $raw);
                    if ($digits !== '' && !preg_match('/^\d{14}$/', $digits)) {
                        flash('error', 'Le numéro SIRET doit comporter exactement 14 chiffres.');
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, $digits);
                    break;

                case 'cp':
                    if ($raw !== '' && !preg_match('/^\d{5}$/', $raw)) {
                        flash('error', 'Le code postal doit comporter 5 chiffres.');
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, $raw);
                    break;

                case 'enum':
                    if (!in_array($raw, $rules['values'] ?? [], true)) {
                        flash('error', "Valeur invalide pour le champ '$cle'.");
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, $raw);
                    break;

                case 'couleur':
                    if ($raw !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $raw)) {
                        flash('error', "La couleur '$cle' doit être au format #RRGGBB.");
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, $raw);
                    break;

                case 'coord':
                    if ($raw !== '' && (!is_numeric($raw) || (float)$raw < -180 || (float)$raw > 180)) {
                        flash('error', "La coordonnée '$cle' est invalide.");
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, $raw);
                    break;

                case 'decimal':
                    if (!is_numeric($raw) || (float)$raw < ($rules['min'] ?? 0)) {
                        flash('error', "La valeur '$cle' est invalide.");
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, number_format((float)$raw, 2, '.', ''));
                    break;

                case 'int':
                    $val = (int)$raw;
                    if ($val < ($rules['min'] ?? 0) || $val > ($rules['max'] ?? PHP_INT_MAX)) {
                        flash('error', "La valeur '$cle' est hors limites.");
                        redirect('/admin/parametres#' . $section);
                    }
                    SiteConfigModel::set($cle, (string)$val);
                    break;
            }
        }

        flash('success', 'Paramètres mis à jour.');
        redirect('/admin/parametres#' . $section);
    }

    public function parametres(): void {
        $config    = SiteConfigModel::getAll();
        $tauxTva   = PricingService::tauxTvaActifs();
        $tousLesToux = db()->fetchAll(
            "SELECT * FROM taux_tva ORDER BY actif DESC, taux ASC, libelle ASC"
        );
        view('pages/admin/parametres', compact('config', 'tauxTva', 'tousLesToux'));
    }

    public function createTauxTva(): void {
        verifyCsrf();
        $libelle   = trim($_POST['libelle']   ?? '');
        $taux      = trim($_POST['taux']      ?? '');
        $categorie = trim($_POST['categorie'] ?? 'general');
        $note      = trim($_POST['note']      ?? '');

        if (!$libelle || !is_numeric($taux) || (float)$taux < 0 || (float)$taux > 100) {
            flash('error', 'Libellé et taux (0–100) sont obligatoires.');
            redirect('/admin/parametres#tva');
        }
        if (!in_array($categorie, ['menu', 'livraison', 'general'], true)) {
            $categorie = 'general';
        }

        db()->execute(
            "INSERT INTO taux_tva (libelle, taux, categorie, actif, par_defaut, note)
             VALUES (?, ?, ?, 1, 0, ?)",
            [$libelle, number_format((float)$taux, 2, '.', ''), $categorie, $note ?: null]
        );
        flash('success', 'Taux TVA créé.');
        redirect('/admin/parametres#tva');
    }

    public function toggleTauxTva(): void {
        verifyCsrf();
        $id    = (int)($_POST['taux_id'] ?? 0);
        $actif = (int)($_POST['actif']   ?? 0);
        if (!$id) { redirect('/admin/parametres#tva'); }

        db()->execute(
            "UPDATE taux_tva SET actif = ? WHERE taux_id = ?",
            [$actif ? 1 : 0, $id]
        );
        flash('success', $actif ? 'Taux activé.' : 'Taux désactivé.');
        redirect('/admin/parametres#tva');
    }

    public function setDefaultTauxTva(): void {
        verifyCsrf();
        $id        = (int)($_POST['taux_id']   ?? 0);
        $categorie = trim($_POST['categorie']  ?? '');
        if (!$id || !in_array($categorie, ['menu', 'livraison', 'general'], true)) {
            redirect('/admin/parametres#tva');
        }

        // Clear existing default for this category, then set the new one
        db()->execute(
            "UPDATE taux_tva SET par_defaut = 0 WHERE categorie = ?",
            [$categorie]
        );
        db()->execute(
            "UPDATE taux_tva SET par_defaut = 1, actif = 1 WHERE taux_id = ?",
            [$id]
        );
        flash('success', 'Taux par défaut mis à jour.');
        redirect('/admin/parametres#tva');
    }

    public function stats(): void {
        $menuFilter = (int)($_GET['menu_id'] ?? 0);
        $dateDebut  = sanitize($_GET['date_debut'] ?? '');
        $dateFin    = sanitize($_GET['date_fin'] ?? '');

        $caStats   = StatsService::getCaParMenu($menuFilter, $dateDebut, $dateFin);
        $synthese  = StatsService::getSynthese($dateDebut, $dateFin);
        $caMensuel = StatsService::getCaMensuel(24, $dateDebut, $dateFin);
        $menus     = \MenuModel::getAll();
        $regimeTva = PricingService::regimeTva();

        view('pages/admin/stats', compact(
            'caStats', 'synthese', 'caMensuel', 'menus', 'regimeTva',
            'menuFilter', 'dateDebut', 'dateFin'
        ));
    }

    public function exportStats(): void {
        $dateDebut = sanitize($_GET['date_debut'] ?? '');
        $dateFin   = sanitize($_GET['date_fin'] ?? '');

        $rows = StatsService::getExportRows($dateDebut, $dateFin);

        $filename = 'ca_vite_gourmand_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'N° commande', 'Date comptabilisation', 'Date prestation',
            'Ville', 'Client', 'Email client', 'Nb personnes',
            'Total HT', 'TVA', 'Total TTC',
            'Encaissé', 'Solde restant', 'Statut paiement', 'Statut commande',
        ], ';');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['numero_commande'],
                $row['date_comptabilisation'],
                $row['date_prestation'],
                $row['ville_livraison'],
                $row['client'],
                $row['client_email'],
                $row['nb_personnes'],
                number_format((float)$row['total_ht'],        2, ',', ''),
                number_format((float)$row['total_tva'],       2, ',', ''),
                number_format((float)$row['total_ttc'],       2, ',', ''),
                number_format((float)$row['montant_encaisse'],2, ',', ''),
                number_format((float)$row['solde_restant'],   2, ',', ''),
                $row['statut_paiement'],
                $row['statut'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function comptabilite(): void {
        $config    = SiteConfigModel::getAll();
        $regimeTva = PricingService::regimeTva();
        $synthese  = StatsService::getSynthese();
        $pageTitle = buildPageTitle('Comptabilité');
        view('pages/admin/comptabilite', compact('config', 'regimeTva', 'synthese', 'pageTitle'));
    }

    public function exportComptabilite(): void {
        $format    = sanitize($_GET['format']     ?? 'commandes');
        $dateDebut = sanitize($_GET['date_debut'] ?? '');
        $dateFin   = sanitize($_GET['date_fin']   ?? '');
        $regimeTva = PricingService::regimeTva();
        $isAssujetti = $regimeTva === 'assujetti';

        $periodSuffix = '';
        if ($dateDebut && $dateFin) {
            $periodSuffix = '_' . $dateDebut . '_' . $dateFin;
        } elseif ($dateDebut) {
            $periodSuffix = '_depuis_' . $dateDebut;
        } elseif ($dateFin) {
            $periodSuffix = '_jusqu_' . $dateFin;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM pour Excel

        switch ($format) {

            case 'lignes':
                header('Content-Disposition: attachment; filename="journal_lignes_vg' . $periodSuffix . '.csv"');
                $headers = [
                    'N° commande', 'Date comptabilisation', 'Date prestation',
                    'Ville', 'Client', 'Email', 'Menu', 'Personnes',
                    'Prix brut', 'Remise', 'Net menu', 'Livraison', 'Total TTC',
                ];
                if ($isAssujetti) {
                    $headers[] = 'TVA %';
                    $headers[] = 'Total HT';
                    $headers[] = 'TVA';
                }
                fputcsv($out, $headers, ';');
                foreach (StatsService::getExportLignes($dateDebut, $dateFin) as $r) {
                    $row = [
                        $r['numero_commande'],
                        $r['date_comptabilisation'],
                        $r['date_prestation'],
                        $r['ville_livraison'],
                        $r['client'],
                        $r['client_email'],
                        $r['menu_titre'],
                        $r['nombre_personne'],
                        number_format((float)$r['prix_brut_menu'],    2, ',', ''),
                        number_format((float)$r['remise'],            2, ',', ''),
                        number_format((float)$r['prix_net_menu'],     2, ',', ''),
                        number_format((float)$r['frais_livraison'],   2, ',', ''),
                        number_format((float)$r['total_ligne_ttc'],   2, ',', ''),
                    ];
                    if ($isAssujetti) {
                        $row[] = number_format((float)$r['taux_tva'],       2, ',', '');
                        $row[] = number_format((float)$r['total_ligne_ht'], 2, ',', '');
                        $row[] = number_format((float)$r['tva_ligne'],      2, ',', '');
                    }
                    fputcsv($out, $row, ';');
                }
                break;

            case 'mensuel':
                header('Content-Disposition: attachment; filename="ca_mensuel_vg' . $periodSuffix . '.csv"');
                $headers = ['Mois', 'Commandes', 'Personnes', 'Panier moyen TTC', 'CA TTC'];
                if ($isAssujetti) {
                    $headers[] = 'CA HT';
                    $headers[] = 'TVA collectée';
                }
                fputcsv($out, $headers, ';');
                foreach (StatsService::getExportMensuel($dateDebut, $dateFin) as $r) {
                    $row = [
                        $r['annee_mois'],
                        $r['nb_commandes'],
                        $r['nb_personnes'],
                        number_format((float)$r['panier_moyen_ttc'], 2, ',', ''),
                        number_format((float)$r['ca_ttc'],           2, ',', ''),
                    ];
                    if ($isAssujetti) {
                        $row[] = number_format((float)$r['ca_ht'],         2, ',', '');
                        $row[] = number_format((float)$r['tva_collectee'], 2, ',', '');
                    }
                    fputcsv($out, $row, ';');
                }
                break;

            default: // 'commandes'
                header('Content-Disposition: attachment; filename="journal_commandes_vg' . $periodSuffix . '.csv"');
                $headers = [
                    'N° commande', 'Date comptabilisation', 'Date prestation',
                    'Ville', 'Client', 'Email', 'Personnes', 'Total TTC',
                ];
                if ($isAssujetti) {
                    $headers[] = 'Total HT';
                    $headers[] = 'TVA';
                }
                array_push($headers, 'Encaissé', 'Solde restant', 'Statut paiement', 'Statut commande');
                fputcsv($out, $headers, ';');
                foreach (StatsService::getExportRows($dateDebut, $dateFin) as $r) {
                    $row = [
                        $r['numero_commande'],
                        $r['date_comptabilisation'],
                        $r['date_prestation'],
                        $r['ville_livraison'],
                        $r['client'],
                        $r['client_email'],
                        $r['nb_personnes'],
                        number_format((float)$r['total_ttc'],        2, ',', ''),
                    ];
                    if ($isAssujetti) {
                        $row[] = number_format((float)$r['total_ht'],  2, ',', '');
                        $row[] = number_format((float)$r['total_tva'], 2, ',', '');
                    }
                    array_push($row,
                        number_format((float)$r['montant_encaisse'], 2, ',', ''),
                        number_format((float)$r['solde_restant'],    2, ',', ''),
                        $r['statut_paiement'],
                        $r['statut']
                    );
                    fputcsv($out, $row, ';');
                }
                break;
        }

        fclose($out);
        exit;
    }

}
