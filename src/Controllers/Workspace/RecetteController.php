<?php

namespace App\Controllers\Workspace;

use App\Models\IngredientModel;
use App\Models\RecetteModel;
use App\Models\StockModel;
use App\Models\MenuModel;

class RecetteController
{
    public function index(): void
    {
        $plats       = MenuModel::getPlatsForAdmin();
        $ingredients = IngredientModel::getAll();
        $alertes     = IngredientModel::getSousSeuilAlerte();

        $recettesByPlat = [];
        $coutsByPlat    = [];
        foreach ($plats as $plat) {
            $pid = (int)$plat['plat_id'];
            $recettesByPlat[$pid] = RecetteModel::getByPlat($pid);
            $coutsByPlat[$pid]    = RecetteModel::coutRevient($pid);
        }

        $mouvements = StockModel::getTousMovements(100);

        view('pages/employe/recettes', compact(
            'plats', 'ingredients', 'alertes',
            'recettesByPlat', 'coutsByPlat', 'mouvements'
        ));
    }

    public function saveRecette(): void
    {
        verifyCsrf();

        $platId = (int)($_POST['plat_id'] ?? 0);
        if (!$platId) {
            flash('error', 'Plat invalide.');
            redirect('/employe/recettes');
        }

        $lignes = [];
        foreach ($_POST['ingredient_id'] ?? [] as $k => $ingredientId) {
            $lignes[] = [
                'ingredient_id' => (int)$ingredientId,
                'grammage'      => (float)str_replace(',', '.', $_POST['grammage'][$k] ?? 0),
            ];
        }

        try {
            RecetteModel::syncLignes($platId, $lignes);
            flash('success', 'Fiche technique enregistrée.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes#plat-' . $platId);
    }

    public function createIngredient(): void
    {
        verifyCsrf();

        $data = [
            'libelle'       => sanitize(trim($_POST['libelle'] ?? '')),
            'unite'         => sanitize(trim($_POST['unite'] ?? 'kg')),
            'prix_unitaire' => (float)str_replace(',', '.', $_POST['prix_unitaire'] ?? 0),
            'seuil_alerte'  => trim($_POST['seuil_alerte'] ?? ''),
        ];

        if (!$data['libelle']) {
            flash('error', 'Le libellé est obligatoire.');
            redirect('/employe/recettes');
        }

        try {
            IngredientModel::create($data);
            flash('success', 'Ingrédient créé.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }

    public function updateIngredient(): void
    {
        verifyCsrf();

        $id   = (int)($_POST['ingredient_id'] ?? 0);
        $data = [
            'libelle'       => sanitize(trim($_POST['libelle'] ?? '')),
            'unite'         => sanitize(trim($_POST['unite'] ?? 'kg')),
            'prix_unitaire' => (float)str_replace(',', '.', $_POST['prix_unitaire'] ?? 0),
            'seuil_alerte'  => trim($_POST['seuil_alerte'] ?? ''),
        ];

        if (!$id || !$data['libelle']) {
            flash('error', 'Données invalides.');
            redirect('/employe/recettes');
        }

        try {
            IngredientModel::update($id, $data);
            flash('success', 'Ingrédient mis à jour.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }

    public function deleteIngredient(): void
    {
        verifyCsrf();

        $id = (int)($_POST['ingredient_id'] ?? 0);
        try {
            IngredientModel::delete($id);
            flash('success', 'Ingrédient supprimé.');
        } catch (\Throwable $e) {
            flash('error', 'Impossible de supprimer : ' . $e->getMessage());
        }
        redirect('/employe/recettes');
    }

    public function addMouvement(): void
    {
        verifyCsrf();

        $ingredientId = (int)($_POST['ingredient_id'] ?? 0);
        $type         = sanitize($_POST['type_mouvement'] ?? '');
        $quantite     = (float)str_replace(',', '.', $_POST['quantite'] ?? 0);
        $motif        = sanitize(trim($_POST['motif'] ?? ''));
        $user         = currentUser();

        try {
            StockModel::addMouvement($ingredientId, $type, $quantite, $motif ?: null, null, $user['id'] ?? null);
            flash('success', 'Mouvement de stock enregistré.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }

    public function deleteMouvement(): void
    {
        verifyCsrf();

        $id = (int)($_POST['mouvement_id'] ?? 0);
        try {
            StockModel::deleteMouvement($id);
            flash('success', 'Mouvement supprimé.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }
}
