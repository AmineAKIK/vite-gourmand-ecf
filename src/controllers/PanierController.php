<?php
// src/controllers/PanierController.php

class PanierController
{
    public function view(): void
    {
        requireAuth();
        $panier = $_SESSION['panier'] ?? [];
        view('pages/panier/index', compact('panier'));
    }

    public function add(): void
    {
        requireAuth();
        verifyCsrf();

        $menuId        = (int)($_POST['menu_id'] ?? 0);
        $nbPersonnes   = (int)($_POST['nombre_personne'] ?? 0);

        $menu = MenuModel::getById($menuId);

        if (!$menu || !$menu['actif']) {
            flash('error', 'Menu introuvable.');
            redirect('/menus');
        }

        if ($menu['quantite_restante'] !== null && (int)$menu['quantite_restante'] <= 0) {
            flash('error', 'Ce menu n\'est plus disponible.');
            redirect('/menus');
        }

        $minimum = (int)$menu['nombre_personne_minimum'];
        if ($nbPersonnes < $minimum) {
            flash('error', 'Nombre de personnes insuffisant (minimum : ' . $minimum . ').');
            redirect($_SERVER['HTTP_REFERER'] ?? '/menus');
        }
        if ($nbPersonnes > 500) {
            flash('error', 'Nombre de personnes trop élevé (maximum : 500).');
            redirect($_SERVER['HTTP_REFERER'] ?? '/menus');
        }

        $prixMenu = calculPrixMenu(
            (float)$menu['prix_par_personne'],
            $nbPersonnes,
            $minimum
        );

        if (!isset($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
        }

        // Fusionner si le même menu est déjà dans le panier
        $retour = $_SERVER['HTTP_REFERER'] ?? '/menus';

        foreach ($_SESSION['panier'] as &$item) {
            if ((int)$item['menu_id'] === $menuId) {
                $item['nombre_personne'] += $nbPersonnes;
                $item['prix_menu'] = calculPrixMenu(
                    (float)$menu['prix_par_personne'],
                    $item['nombre_personne'],
                    $minimum
                );
                flash('success', '« ' . $menu['titre'] . ' » mis à jour dans votre panier. <a href="/panier" class="alert-link">Voir le panier</a>');
                redirect($retour);
            }
        }
        unset($item);

        $_SESSION['panier'][] = [
            'menu_id'          => $menuId,
            'titre'            => $menu['titre'],
            'prix_par_personne'=> (float)$menu['prix_par_personne'],
            'minimum'          => $minimum,
            'nombre_personne'  => $nbPersonnes,
            'prix_menu'        => $prixMenu,
        ];

        flash('success', '« ' . $menu['titre'] . ' » ajouté à votre panier. <a href="/panier" class="alert-link">Voir le panier</a>');
        redirect($retour);
    }

    public function remove(): void
    {
        requireAuth();
        verifyCsrf();

        $index = (int)($_POST['index'] ?? -1);
        $panier = $_SESSION['panier'] ?? [];

        if (isset($panier[$index])) {
            $titre = $panier[$index]['titre'];
            array_splice($_SESSION['panier'], $index, 1);
            flash('success', '« ' . $titre . ' » retiré du panier.');
        }

        redirect('/panier');
    }

    public function clear(): void
    {
        requireAuth();
        verifyCsrf();
        $_SESSION['panier'] = [];
        flash('success', 'Panier vidé.');
        redirect('/menus');
    }
}
