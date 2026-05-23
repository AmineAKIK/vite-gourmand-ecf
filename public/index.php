<?php
// public/index.php - Point d'entrée unique

require_once __DIR__ . '/../src/config/config.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => (APP_ENV !== 'development'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Timeout d'inactivité 30 minutes
$_maxIdle = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $_maxIdle) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();
unset($_maxIdle);

// Nonce CSP — généré par requête, transmis aux vues via $GLOBALS
$GLOBALS['csp_nonce'] = base64_encode(random_bytes(16));

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'nonce-" . $GLOBALS['csp_nonce'] . "'; "
    . "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'nonce-" . $GLOBALS['csp_nonce'] . "'; "
    . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; "
    . "img-src 'self' data: https:; "
    . "connect-src 'self'; "
    . "frame-ancestors 'none';"
);
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

// Healthcheck — réponse avant tout dispatch applicatif
if ($uri === '/health') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        Database::getConnection()->query('SELECT 1');
        echo json_encode(['status' => 'ok', 'db' => 'ok', 'ts' => time()]);
    } catch (\Throwable) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'db' => 'down', 'ts' => time()]);
    }
    exit;
}
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
        '/employe'                        => ['EmployeController', 'dashboard'],
        '/employe/commandes'              => ['EmployeController', 'commandes'],
        '/employe/menus'                  => ['EmployeController', 'menus'],
        '/employe/avis'                   => ['EmployeController', 'avis'],
        '/employe/horaires'               => ['EmployeController', 'horaires'],
        '/employe/changer-mot-de-passe'   => ['EmployeController', 'changePasswordForm'],
    ],
    'GET_ADMIN' => [
        '/admin'               => ['AdminController',   'dashboard'],
        '/admin/employes'      => ['AdminController',   'employes'],
        '/admin/stats'         => ['AdminController',   'stats'],
        '/admin/accueil'       => ['AdminController',   'accueil'],
        '/admin/images'        => ['AdminController',   'images'],
        '/admin/parametres'    => ['AdminController',   'parametres'],
    ],
    'POST_EMPLOYE' => [
        '/employe/changer-mot-de-passe'  => ['EmployeController', 'changePassword'],
        '/employe/commande/statut'   => ['EmployeController', 'updateStatut'],
        '/employe/menu/creer'        => ['EmployeController', 'createMenu'],
        '/employe/menu/modifier'     => ['EmployeController', 'updateMenu'],
        '/employe/menu/supprimer'    => ['EmployeController', 'deleteMenu'],
        '/employe/avis/valider'      => ['EmployeController', 'validerAvis'],
        '/employe/avis/supprimer'    => ['EmployeController', 'supprimerAvis'],
        '/employe/horaires/modifier' => ['EmployeController', 'updateHoraires'],
        '/employe/plat/creer'           => ['EmployeController', 'createPlat'],
        '/employe/plat/modifier'        => ['EmployeController', 'updatePlat'],
        '/employe/plat/supprimer'       => ['EmployeController', 'deletePlat'],
        '/employe/menu/image/supprimer' => ['EmployeController', 'deleteMenuImage'],
    ],
    'POST_ADMIN' => [
        '/admin/employe/creer'      => ['AdminController', 'createEmploye'],
        '/admin/employe/desactiver' => ['AdminController', 'disableEmploye'],
        '/admin/employe/supprimer'  => ['AdminController', 'deleteEmploye'],
        '/admin/accueil/modifier'      => ['AdminController', 'updateAccueil'],
        '/admin/images/modifier'       => ['AdminController', 'updateImages'],
        '/admin/parametres/modifier'   => ['AdminController', 'updateParametres'],
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
    try {
        $controller = new $controllerClass();
        $controller->$action();
    } catch (Throwable $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        view('pages/500');
    }
} else {
    http_response_code(404);
    view('pages/404');
}
