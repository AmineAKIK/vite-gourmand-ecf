<?php
// public/index.php - Point d'entrée unique

session_start();
require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/helpers.php';

// Autoload simplifié
spl_autoload_register(function($class) {
    $paths = [
        __DIR__ . '/../src/controllers/' . $class . '.php',
        __DIR__ . '/../src/models/'      . $class . '.php',
        __DIR__ . '/../src/services/'    . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) { require_once $path; return; }
    }
});

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    'GET'  => [
        '/'                    => ['HomeController',    'index'],
        '/menus'               => ['MenuController',    'index'],
        '/menus/detail'        => ['MenuController',    'detail'],
        '/livraison/calcul'    => ['CommandeController','calculLivraison'],
        '/connexion'           => ['AuthController',    'loginForm'],
        '/inscription'         => ['AuthController',    'registerForm'],
        '/deconnexion'         => ['AuthController',    'logout'],
        '/mot-de-passe-oublie' => ['AuthController',    'forgotForm'],
        '/reinitialiser'       => ['AuthController',    'resetForm'],
        '/contact'             => ['ContactController', 'index'],
        '/mentions-legales'    => ['PageController',    'mentions'],
        '/cgv'                 => ['PageController',    'cgv'],
    ],
    'POST' => [
        '/connexion'           => ['AuthController',    'login'],
        '/inscription'         => ['AuthController',    'register'],
        '/mot-de-passe-oublie' => ['AuthController',    'forgot'],
        '/reinitialiser'       => ['AuthController',    'reset'],
        '/contact'             => ['ContactController', 'send'],
    ],
    'GET_AUTH' => [
        '/mon-compte'          => ['UserController',    'dashboard'],
        '/commande'            => ['CommandeController','form'],
        '/commande/suivi'      => ['CommandeController','suivi'],
    ],
    'POST_AUTH' => [
        '/mon-compte/modifier'  => ['UserController', 'update'],
        '/mon-compte/supprimer' => ['UserController', 'deleteAccount'],
        '/commande'            => ['CommandeController','create'],
        '/commande/modifier'   => ['CommandeController','update'],
        '/commande/annuler'    => ['CommandeController','cancel'],
        '/avis'                => ['AvisController',    'create'],
    ],
    'GET_EMPLOYE' => [
        '/employe'              => ['EmployeController', 'dashboard'],
        '/employe/commandes'    => ['EmployeController', 'commandes'],
        '/employe/menus'        => ['EmployeController', 'menus'],
        '/employe/avis'         => ['EmployeController', 'avis'],
        '/employe/horaires'     => ['EmployeController', 'horaires'],
    ],
    'GET_ADMIN' => [
        '/admin'               => ['AdminController',   'dashboard'],
        '/admin/employes'      => ['AdminController',   'employes'],
        '/admin/stats'         => ['AdminController',   'stats'],
    ],
    'POST_EMPLOYE' => [
        '/employe/commande/statut'   => ['EmployeController', 'updateStatut'],
        '/employe/menu/creer'        => ['EmployeController', 'createMenu'],
        '/employe/menu/modifier'     => ['EmployeController', 'updateMenu'],
        '/employe/menu/supprimer'    => ['EmployeController', 'deleteMenu'],
        '/employe/avis/valider'      => ['EmployeController', 'validerAvis'],
        '/employe/horaires/modifier' => ['EmployeController', 'updateHoraires'],
        '/employe/plat/creer'           => ['EmployeController', 'createPlat'],
        '/employe/plat/modifier'        => ['EmployeController', 'updatePlat'],
        '/employe/plat/supprimer'       => ['EmployeController', 'deletePlat'],
        '/employe/menu/image/supprimer' => ['EmployeController', 'deleteMenuImage'],
    ],
    'POST_ADMIN' => [
        '/admin/employe/creer'      => ['AdminController', 'createEmploye'],
        '/admin/employe/desactiver' => ['AdminController', 'disableEmploye'],
    ],
];

function resolveRoute($routes, $method, $uri): ?array {
    if (isset($routes[$method][$uri])) return $routes[$method][$uri];

    if ($method === 'GET' && isset($routes['GET_AUTH'][$uri])) {
        requireAuth(); return $routes['GET_AUTH'][$uri];
    }
    if ($method === 'POST' && isset($routes['POST_AUTH'][$uri])) {
        requireAuth(); return $routes['POST_AUTH'][$uri];
    }
    if ($method === 'GET' && isset($routes['GET_EMPLOYE'][$uri])) {
        requireRole([ROLE_EMPLOYE, ROLE_ADMIN]); return $routes['GET_EMPLOYE'][$uri];
    }
    if ($method === 'GET' && isset($routes['GET_ADMIN'][$uri])) {
        requireRole([ROLE_ADMIN]); return $routes['GET_ADMIN'][$uri];
    }
    if ($method === 'POST' && isset($routes['POST_EMPLOYE'][$uri])) {
        requireRole([ROLE_EMPLOYE, ROLE_ADMIN]); return $routes['POST_EMPLOYE'][$uri];
    }
    if ($method === 'POST' && isset($routes['POST_ADMIN'][$uri])) {
        requireRole([ROLE_ADMIN]); return $routes['POST_ADMIN'][$uri];
    }
    return null;
}

$route = resolveRoute($routes, $method, $uri);
if ($route) {
    [$controllerClass, $action] = $route;
    $controller = new $controllerClass();
    $controller->$action();
} else {
    http_response_code(404);
    view('pages/404');
}
