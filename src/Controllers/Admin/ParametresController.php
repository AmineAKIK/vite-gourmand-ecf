<?php

namespace App\Controllers\Admin;

use App\Models\HoraireModel;
use App\Models\SiteConfigModel;
use App\Models\SiteImageModel;
use App\Services\MenuAdminService;
use App\Services\PricingService;

class ParametresController
{
    public function index(): void
    {
        $config      = SiteConfigModel::getAll();
        $tauxTva     = PricingService::tauxTvaActifs();
        $tousLesToux = db()->fetchAll(
            'SELECT * FROM taux_tva ORDER BY actif DESC, taux ASC, libelle ASC'
        );
        $images   = SiteImageModel::getAll();
        $horaires = HoraireModel::getAll();
        view('pages/admin/parametres', compact('config', 'tauxTva', 'tousLesToux', 'images', 'horaires'));
    }

    public function update(): void
    {
        verifyCsrf();

        $section   = $_POST['_section'] ?? 'tarification';
        $allFields = [
            'identite' => [
                'site_nom'                         => ['type' => 'string',  'max' => 100],
                'site_slogan'                      => ['type' => 'string',  'max' => 100],
                'site_domaine'                     => ['type' => 'string',  'max' => 100],
                'site_email'                       => ['type' => 'email'],
                'site_telephone'                   => ['type' => 'string',  'max' => 30],
                'site_adresse'                     => ['type' => 'string',  'max' => 150],
                'site_code_postal'                 => ['type' => 'cp'],
                'site_ville'                       => ['type' => 'string',  'max' => 80],
                'couleur_principale'               => ['type' => 'couleur'],
                'couleur_secondaire'               => ['type' => 'couleur'],
                'couleur_fond'                     => ['type' => 'couleur'],
                'livraison_lat'                    => ['type' => 'coord'],
                'livraison_lng'                    => ['type' => 'coord'],
                'livraison_codes_postaux_gratuits' => ['type' => 'string',  'max' => 500],
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
                'regime_tva'      => ['type' => 'enum', 'values' => ['assujetti', 'non_assujetti']],
                'mention_facture' => ['type' => 'string', 'max' => 500],
                'mention_ticket'  => ['type' => 'string', 'max' => 500],
                'mention_acompte' => ['type' => 'string', 'max' => 500],
            ],
            'paiement' => [
                'acompte_taux_defaut'    => ['type' => 'int',     'min' => 0,   'max' => 100],
                'delai_paiement_jours'   => ['type' => 'int',     'min' => 0,   'max' => 365],
                'penalites_retard_taux'  => ['type' => 'decimal', 'min' => 0],
                'indemnite_recouvrement' => ['type' => 'decimal', 'min' => 0],
            ],
            'tarification' => [
                'hero_sous_titre'  => ['type' => 'string',  'max' => 60],
                'hero_paragraphe'  => ['type' => 'string',  'max' => 200],
                'livraison_base'   => ['type' => 'decimal', 'min' => 0],
                'livraison_km'     => ['type' => 'decimal', 'min' => 0],
                'reduction_seuil'  => ['type' => 'decimal', 'min' => 0],
                'reduction_taux'   => ['type' => 'int',     'min' => 0, 'max' => 100],
            ],
            'legal' => [
                'cgv_contenu'      => ['type' => 'string', 'max' => 20000],
                'mentions_contenu' => ['type' => 'string', 'max' => 20000],
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

    public function createTauxTva(): void
    {
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
            'INSERT INTO taux_tva (libelle, taux, categorie, actif, par_defaut, note) VALUES (?, ?, ?, 1, 0, ?)',
            [$libelle, number_format((float)$taux, 2, '.', ''), $categorie, $note ?: null]
        );
        flash('success', 'Taux TVA créé.');
        redirect('/admin/parametres#tva');
    }

    public function toggleTauxTva(): void
    {
        verifyCsrf();

        $id    = (int)($_POST['taux_id'] ?? 0);
        $actif = (int)($_POST['actif']   ?? 0);
        if (!$id) {
            redirect('/admin/parametres#tva');
        }

        db()->execute('UPDATE taux_tva SET actif = ? WHERE taux_id = ?', [$actif ? 1 : 0, $id]);
        flash('success', $actif ? 'Taux activé.' : 'Taux désactivé.');
        redirect('/admin/parametres#tva');
    }

    public function setDefaultTauxTva(): void
    {
        verifyCsrf();

        $id        = (int)($_POST['taux_id']  ?? 0);
        $categorie = trim($_POST['categorie'] ?? '');
        if (!$id || !in_array($categorie, ['menu', 'livraison', 'general'], true)) {
            redirect('/admin/parametres#tva');
        }

        db()->execute('UPDATE taux_tva SET par_defaut = 0 WHERE categorie = ?', [$categorie]);
        db()->execute('UPDATE taux_tva SET par_defaut = 1, actif = 1 WHERE taux_id = ?', [$id]);
        flash('success', 'Taux par défaut mis à jour.');
        redirect('/admin/parametres#tva');
    }

    public function accueil(): void
    {
        \App\Core\View::redirect('/admin/parametres?tab=personnalisation');
    }

    public function updateAccueil(): void
    {
        verifyCsrf();

        $sousTitre  = trim($_POST['hero_sous_titre'] ?? '');
        $paragraphe = trim($_POST['hero_paragraphe'] ?? '', " \t\r");

        if (mb_strlen($sousTitre) > 60) {
            flash('error', 'Le sous-titre ne peut pas dépasser 60 caractères.');
            redirect('/admin/parametres#personnalisation');
        }
        if (mb_strlen($paragraphe) > 200) {
            flash('error', 'Le paragraphe ne peut pas dépasser 200 caractères.');
            redirect('/admin/parametres#personnalisation');
        }

        SiteConfigModel::set('hero_sous_titre', $sousTitre);
        SiteConfigModel::set('hero_paragraphe', $paragraphe);

        foreach (['logo', 'favicon', 'og_image', 'hero', 'preparation'] as $cle) {
            $file = $_FILES[$cle] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $url = MenuAdminService::uploadSiteImage($file, 'site/' . $cle);
            if ($url) {
                SiteImageModel::set($cle, $url);
            } else {
                flash('error', 'Erreur lors de l\'upload de l\'image "' . $cle . '".');
                redirect('/admin/parametres#personnalisation');
            }
        }

        flash('success', 'Page d\'accueil mise à jour.');
        redirect('/admin/parametres#personnalisation');
    }

}
