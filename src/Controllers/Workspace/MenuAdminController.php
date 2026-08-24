<?php

namespace App\Controllers\Workspace;

use App\Models\MenuModel;
use App\Services\CatalogIntegrityService;
use App\Services\MenuAdminService;
use Throwable;

class MenuAdminController
{
    public function index(): void
    {
        $menus = MenuModel::getAll();
        $themes = MenuModel::getThemes();
        $regimes = MenuModel::getRegimes();
        $plats = MenuModel::getPlatsForAdmin();
        $categories = MenuModel::getCategories();
        $allergens = MenuModel::getAllergens();
        $platsByMenu = MenuModel::getPlatsByMenu();
        $imagesByMenu = MenuModel::getImagesByMenuIds(array_column($menus, 'menu_id'));

        view('pages/employe/menus', compact('menus', 'themes', 'regimes', 'plats', 'categories', 'allergens', 'platsByMenu', 'imagesByMenu'));
    }

    public function createMenu(): void
    {
        verifyCsrf();
        $paths = [];
        try {
            $data = MenuAdminService::menuPayloadFromRequest($_POST);
            $platIds = MenuAdminService::selectedIds($_POST, 'plats');
            $paths = MenuAdminService::prepareMenuImages($_FILES['images'] ?? [], true);
            CatalogIntegrityService::createMenu($data, $platIds, $paths);
            flash('success', 'Menu créé avec succès.');
            redirect('/employe/menus');
        } catch (Throwable $e) {
            if ($paths !== []) {
                MenuAdminService::cleanupStoredImages($paths);
            }
            redirect('/employe/menus?open_modal=creer_menu&modal_error=' . urlencode($e->getMessage()));
        }
    }

    public function updateMenu(): void
    {
        verifyCsrf();

        $id = (int) ($_POST['menu_id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Menu introuvable.');
            redirect('/employe/menus');
        }

        $paths = [];
        try {
            $data = MenuAdminService::menuPayloadFromRequest($_POST);
            $platIds = MenuAdminService::selectedIds($_POST, 'plats');
            $paths = MenuAdminService::prepareMenuImages($_FILES['images'] ?? [], false);
            CatalogIntegrityService::updateMenu($id, $data, $platIds, $paths);
            flash('success', 'Menu modifié avec succès.');
            redirect('/employe/menus');
        } catch (Throwable $e) {
            if ($paths !== []) {
                MenuAdminService::cleanupStoredImages($paths);
            }
            redirect('/employe/menus?open_modal=modifier_menu_' . $id . '&modal_error=' . urlencode($e->getMessage()));
        }
    }

    public function deleteMenu(): void
    {
        verifyCsrf();
        MenuModel::delete((int) ($_POST['menu_id'] ?? 0));
        flash('success', 'Menu supprimé.');
        redirect('/employe/menus');
    }

    public function createPlat(): void
    {
        verifyCsrf();
        try {
            CatalogIntegrityService::createPlat(MenuAdminService::platPayloadFromRequest($_POST));
            flash('success', 'Plat créé avec succès.');
            redirect('/employe/menus');
        } catch (Throwable $e) {
            redirect('/employe/menus?open_modal=creer_plat&modal_error=' . urlencode($e->getMessage()));
        }
    }

    public function updatePlat(): void
    {
        verifyCsrf();
        $id = (int) ($_POST['plat_id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Plat introuvable.');
            redirect('/employe/menus');
        }

        try {
            CatalogIntegrityService::updatePlat($id, MenuAdminService::platPayloadFromRequest($_POST));
            flash('success', 'Plat modifié.');
            redirect('/employe/menus');
        } catch (Throwable $e) {
            redirect('/employe/menus?open_modal=modifier_plat_' . $id . '&modal_error=' . urlencode($e->getMessage()));
        }
    }

    public function deletePlat(): void
    {
        verifyCsrf();
        $platId = (int) ($_POST['plat_id'] ?? 0);
        if (MenuModel::platIsUsed($platId)) {
            flash('error', 'Impossible de supprimer un plat utilisé dans un menu. Retirez-le d’abord des menus concernés.');
            redirect('/employe/menus');
        }

        try {
            MenuModel::deletePlat($platId);
            flash('success', 'Plat supprimé.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/menus');
    }

    public function deleteMenuImage(): void
    {
        verifyCsrf();
        try {
            $detached = CatalogIntegrityService::detachImage((int) ($_POST['image_id'] ?? 0));
            if ($detached !== null) {
                try {
                    MenuAdminService::deleteStoredImagePath($detached['path']);
                } catch (Throwable $cleanupError) {
                    error_log('[menu-image] detached file cleanup failed: ' . $cleanupError->getMessage());
                }
            }
            flash('success', 'Image supprimée.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/employe/menus');
    }
}
