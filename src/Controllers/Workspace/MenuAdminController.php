<?php

namespace App\Controllers\Workspace;

use App\Models\MenuModel;
use App\Services\MenuAdminService;

class MenuAdminController
{
    public function index(): void
    {
        $menus        = MenuModel::getAll();
        $themes       = MenuModel::getThemes();
        $regimes      = MenuModel::getRegimes();
        $plats        = MenuModel::getPlatsForAdmin();
        $categories   = MenuModel::getCategories();
        $allergens    = MenuModel::getAllergens();
        $platsByMenu  = MenuModel::getPlatsByMenu();
        $imagesByMenu = MenuModel::getImagesByMenuIds(array_column($menus, 'menu_id'));

        view('pages/employe/menus', compact('menus', 'themes', 'regimes', 'plats', 'categories', 'allergens', 'platsByMenu', 'imagesByMenu'));
    }

    public function createMenu(): void
    {
        verifyCsrf();

        try {
            $data = MenuAdminService::menuPayloadFromRequest($_POST);
        } catch (\InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=creer_menu&modal_error=' . urlencode($e->getMessage()));
        }

        $images = $_FILES['images'] ?? [];
        if (empty($images['name'][0]) || ($images['error'][0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            redirect('/employe/menus?open_modal=creer_menu&modal_error=' . urlencode('Au moins une photo est obligatoire.'));
        }

        $menuId = MenuModel::create($data);
        MenuModel::addMenuPlats($menuId, MenuAdminService::selectedIds($_POST, 'plats'));
        MenuAdminService::uploadMenuImages($menuId, $images, 1);

        flash('success', 'Menu créé avec succès.');
        redirect('/employe/menus');
    }

    public function updateMenu(): void
    {
        verifyCsrf();

        $id = (int)($_POST['menu_id'] ?? 0);
        if (!$id) {
            flash('error', 'Menu introuvable.');
            redirect('/employe/menus');
        }

        try {
            $data = MenuAdminService::menuPayloadFromRequest($_POST);
        } catch (\InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=modifier_menu_' . $id . '&modal_error=' . urlencode($e->getMessage()));
        }

        MenuModel::update($id, $data);
        MenuModel::replaceMenuPlats($id, MenuAdminService::selectedIds($_POST, 'plats'));
        MenuAdminService::uploadMenuImages($id, $_FILES['images'] ?? [], MenuModel::nextMenuImageOrder($id));

        flash('success', 'Menu modifié avec succès.');
        redirect('/employe/menus');
    }

    public function deleteMenu(): void
    {
        verifyCsrf();
        MenuModel::delete((int)($_POST['menu_id'] ?? 0));
        flash('success', 'Menu supprimé.');
        redirect('/employe/menus');
    }

    public function createPlat(): void
    {
        verifyCsrf();

        try {
            $data = MenuAdminService::platPayloadFromRequest($_POST);
        } catch (\InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=creer_plat&modal_error=' . urlencode($e->getMessage()));
        }

        MenuModel::createPlat($data);

        flash('success', 'Plat créé avec succès.');
        redirect('/employe/menus');
    }

    public function updatePlat(): void
    {
        verifyCsrf();

        $id = (int)($_POST['plat_id'] ?? 0);
        if (!$id) {
            flash('error', 'Plat introuvable.');
            redirect('/employe/menus');
        }

        try {
            $data = MenuAdminService::platPayloadFromRequest($_POST);
        } catch (\InvalidArgumentException $e) {
            redirect('/employe/menus?open_modal=modifier_plat_' . $id . '&modal_error=' . urlencode($e->getMessage()));
        }

        MenuModel::updatePlat($id, $data);

        flash('success', 'Plat modifié.');
        redirect('/employe/menus');
    }

    public function deletePlat(): void
    {
        verifyCsrf();

        $platId = (int)($_POST['plat_id'] ?? 0);
        if (MenuModel::platIsUsed($platId)) {
            flash('error', 'Impossible de supprimer un plat utilisé dans un menu. Retirez-le d\'abord des menus concernés.');
            redirect('/employe/menus');
        }

        MenuModel::deletePlat($platId);
        flash('success', 'Plat supprimé.');
        redirect('/employe/menus');
    }

    public function deleteMenuImage(): void
    {
        verifyCsrf();
        MenuAdminService::deleteMenuImageFile((int)($_POST['image_id'] ?? 0));
        flash('success', 'Image supprimée.');
        redirect('/employe/menus');
    }
}
