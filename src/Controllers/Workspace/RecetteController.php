<?php

namespace App\Controllers\Workspace;

use App\Models\IngredientModel;
use App\Models\MenuModel;
use App\Models\RecetteModel;
use App\Models\StockModel;
use App\Services\RecipeService;
use App\Services\IngredientCatalogService;

class RecetteController
{
    public function index(): void
    {
        $plats = MenuModel::getPlatsForAdmin();
        $ingredients = IngredientModel::getAll(true);
        $alertes = IngredientModel::getSousSeuilAlerte();

        $recettesByPlat = [];
        $coutsByPlat = [];
        foreach ($plats as $plat) {
            $pid = (int) $plat['plat_id'];
            $recettesByPlat[$pid] = RecetteModel::getByPlat($pid);
            $coutsByPlat[$pid] = RecetteModel::coutRevient($pid);
        }

        $mouvements = StockModel::getTousMovements(100);

        view('pages/employe/recettes', compact(
            'plats',
            'ingredients',
            'alertes',
            'recettesByPlat',
            'coutsByPlat',
            'mouvements',
        ));
    }

    public function saveRecette(): void
    {
        verifyCsrf();

        $platId = (int) ($_POST['plat_id'] ?? 0);
        if ($platId <= 0) {
            flash('error', 'Plat invalide.');
            redirect('/employe/recettes');
        }

        $ingredientIds = is_array($_POST['ingredient_id'] ?? null) ? $_POST['ingredient_id'] : [];
        $grammages = is_array($_POST['grammage'] ?? null) ? $_POST['grammage'] : [];
        $lignes = [];
        foreach ($ingredientIds as $key => $ingredientId) {
            $lignes[] = [
                'ingredient_id' => $ingredientId,
                'grammage' => $grammages[$key] ?? null,
            ];
        }

        try {
            RecipeService::save($platId, $lignes);
            flash('success', 'Fiche technique enregistrée.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes#plat-' . $platId);
    }

    public function createIngredient(): void
    {
        verifyCsrf();
        try {
            IngredientCatalogService::create($_POST);
            flash('success', 'Ingrédient créé.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }

    public function updateIngredient(): void
    {
        verifyCsrf();
        $id = (int) ($_POST['ingredient_id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Données invalides.');
            redirect('/employe/recettes');
        }

        try {
            IngredientCatalogService::update($id, $_POST);
            flash('success', 'Ingrédient mis à jour.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }

    public function deleteIngredient(): void
    {
        verifyCsrf();
        $id = (int) ($_POST['ingredient_id'] ?? 0);
        try {
            IngredientCatalogService::deactivate($id);
            flash('success', 'Ingrédient désactivé. Son historique de stock est conservé.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }

    public function addMouvement(): void
    {
        verifyCsrf();

        $ingredientId = (int) ($_POST['ingredient_id'] ?? 0);
        $type = sanitize($_POST['type_mouvement'] ?? '');
        $quantite = trim((string) ($_POST['quantite'] ?? ''));
        $motif = sanitize(trim($_POST['motif'] ?? ''));
        $user = currentUser();

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

        $id = (int) ($_POST['mouvement_id'] ?? 0);
        $user = currentUser();
        try {
            StockModel::deleteMouvement($id, isset($user['id']) ? (int) $user['id'] : null);
            flash('success', 'Mouvement contre-passé. Le ledger conserve l’historique.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/recettes');
    }
}
