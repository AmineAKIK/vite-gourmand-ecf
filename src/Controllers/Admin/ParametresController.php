<?php

namespace App\Controllers\Admin;

use App\Config\ConfigurationInvalidException;
use App\Config\ConfigurationRegistry;
use App\Config\ConfigurationWriter;
use App\Models\HoraireModel;
use App\Models\SiteConfigModel;
use App\Models\SiteImageModel;
use App\Services\MenuAdminService;
use App\Services\PricingService;

class ParametresController
{
    /** @var list<string> */
    private const NO_UI_DEFAULTS = [
        'livraison_lat',
        'livraison_lng',
        'livraison_rayon_max_km',
        'livraison_base',
        'livraison_km',
        'commandes_max_par_jour',
        'reduction_seuil',
        'reduction_taux',
        'regime_tva',
        'acompte_taux_defaut',
        'delai_paiement_jours',
        'penalites_retard_taux',
        'indemnite_recouvrement',
    ];

    public function index(): void
    {
        $storageKeys = self::tenantStorageKeys();
        $storedConfig = SiteConfigModel::getAll();
        $config = array_intersect_key($storedConfig, array_fill_keys($storageKeys, true));

        foreach (self::NO_UI_DEFAULTS as $storageKey) {
            if (!array_key_exists($storageKey, $config)) {
                $config[$storageKey] = '';
            }
        }

        $tauxTva = PricingService::tauxTvaActifs();
        $tousLesToux = db()->fetchAll(
            'SELECT * FROM taux_tva ORDER BY actif DESC, taux ASC, libelle ASC'
        );
        $images = SiteImageModel::getAll();
        $horaires = HoraireModel::getAll();
        view('pages/admin/parametres', compact('config', 'tauxTva', 'tousLesToux', 'images', 'horaires'));
    }

    public function update(): void
    {
        verifyCsrf();

        $section = $this->postedSection();
        $allowedPostKeys = array_merge(self::tenantStorageKeys(), ['csrf_token', '_section']);
        foreach ($_POST as $postKey => $postValue) {
            if (in_array($postKey, $allowedPostKeys, true)) {
                continue;
            }

            if (!is_string($postValue) || trim($postValue) !== '') {
                flash('error', 'Un paramètre non reconnu ou réservé à l’opérateur a été refusé.');
                redirect('/admin/parametres#' . $section);
            }
        }

        $written = false;

        try {
            foreach (ConfigurationRegistry::siteConfigDefinitions() as $definition) {
                $storageKey = $definition->storageKey;
                if ($storageKey === null || !array_key_exists($storageKey, $_POST)) {
                    continue;
                }

                $raw = $_POST[$storageKey];
                if (!is_string($raw)) {
                    throw new ConfigurationInvalidException(
                        'Configuration invalid: ' . $definition->key,
                    );
                }

                ConfigurationWriter::write($definition->key, $raw);
                $written = true;
            }
        } catch (ConfigurationInvalidException) {
            flash('error', 'Un paramètre contient une valeur invalide ou hors limites.');
            redirect('/admin/parametres#' . $section);
        }

        if (!$written) {
            flash('error', 'Aucun paramètre modifiable reconnu.');
            redirect('/admin/parametres#' . $section);
        }

        flash('success', 'Paramètres mis à jour.');
        redirect('/admin/parametres#' . $section);
    }

    public function createTauxTva(): void
    {
        verifyCsrf();

        $libelle = trim($_POST['libelle'] ?? '');
        $taux = trim($_POST['taux'] ?? '');
        $categorie = trim($_POST['categorie'] ?? 'general');
        $note = trim($_POST['note'] ?? '');

        if (!$libelle || !is_numeric($taux) || (float) $taux < 0 || (float) $taux > 100) {
            flash('error', 'Libellé et taux (0–100) sont obligatoires.');
            redirect('/admin/parametres#tva');
        }
        if (!in_array($categorie, ['menu', 'livraison', 'general'], true)) {
            $categorie = 'general';
        }

        db()->execute(
            'INSERT INTO taux_tva (libelle, taux, categorie, actif, par_defaut, note) VALUES (?, ?, ?, 1, 0, ?)',
            [$libelle, number_format((float) $taux, 2, '.', ''), $categorie, $note ?: null]
        );
        flash('success', 'Taux TVA créé.');
        redirect('/admin/parametres#tva');
    }

    public function toggleTauxTva(): void
    {
        verifyCsrf();

        $id = (int) ($_POST['taux_id'] ?? 0);
        $actif = (int) ($_POST['actif'] ?? 0);
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

        $id = (int) ($_POST['taux_id'] ?? 0);
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

        $contentKeys = [
            'hero_sous_titre', 'hero_paragraphe', 'home_intro_titre', 'home_intro_texte',
            'home_cta_libelle', 'home_cta_url', 'home_avis_titre', 'home_avis_description',
            'contact_titre', 'contact_intro', 'contact_delai_reponse_heures', 'footer_texte',
            'seo_home_titre', 'seo_home_description', 'seo_contact_titre', 'seo_contact_description',
        ];
        try {
            foreach ($contentKeys as $storageKey) {
                $raw = $_POST[$storageKey] ?? '';
                if (!is_string($raw)) {
                    throw new ConfigurationInvalidException('Configuration invalid: ' . $storageKey);
                }
                ConfigurationWriter::writeStorageKey($storageKey, trim($raw));
            }
        } catch (ConfigurationInvalidException) {
            flash('error', 'Le contenu de personnalisation est invalide ou trop long.');
            redirect('/admin/parametres#personnalisation');
        }

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

    /** @return list<string> */
    private static function tenantStorageKeys(): array
    {
        $keys = [];
        foreach (ConfigurationRegistry::siteConfigDefinitions() as $definition) {
            if ($definition->storageKey !== null) {
                $keys[] = $definition->storageKey;
            }
        }

        return $keys;
    }

    private function postedSection(): string
    {
        $section = $_POST['_section'] ?? 'identite';
        if (!is_string($section)) {
            return 'identite';
        }

        return in_array(
            $section,
            ['identite', 'entreprise', 'fiscal', 'paiement', 'tarification', 'legal', 'avance'],
            true,
        ) ? $section : 'identite';
    }
}
