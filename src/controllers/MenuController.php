<?php
// src/controllers/MenuController.php

class MenuController {

    public function index(): void {
        $filters = [
            'prix_max'    => $_GET['prix_max']    ?? null,
            'prix_min'    => $_GET['prix_min']    ?? null,
            'theme_id'    => $_GET['theme_id']    ?? null,
            'regime_id'   => $_GET['regime_id']   ?? null,
            'nb_personnes'=> $_GET['nb_personnes'] ?? null,
        ];

        // Si requête AJAX, retourner JSON
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            $menus = array_map(fn($m) => array_merge($m, [
                'description' => html_entity_decode($m['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                'titre'       => html_entity_decode($m['titre']       ?? '', ENT_QUOTES, 'UTF-8'),
            ]), MenuModel::getAll($filters));
            echo json_encode($menus);
            exit;
        }

        $menus   = MenuModel::getAll($filters);
        $themes  = MenuModel::getThemes();
        $regimes = MenuModel::getRegimes();
        view('pages/menus/index', compact('menus', 'themes', 'regimes', 'filters'));
    }

    public function detail(): void {
        $id   = (int)($_GET['id'] ?? 0);
        $menu = MenuModel::getById($id);

        if (!$menu) {
            http_response_code(404);
            view('pages/404'); return;
        }
        view('pages/menus/detail', compact('menu'));
    }
}
