<?php

require_once __DIR__ . '/../src/Config/config.php';

use App\Config\Database;
use App\Config\Migrator;
use App\Controllers\AuthController;
use App\Controllers\CronController;
use App\Controllers\DevisController;
use App\Controllers\AvisController;
use App\Controllers\CommandeController;
use App\Controllers\ContactController;
use App\Controllers\HomeController;
use App\Controllers\MenuController;
use App\Controllers\PageController;
use App\Controllers\PaiementController;
use App\Controllers\PanierController;
use App\Controllers\StripeController;
use App\Controllers\UserController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EmployeAdminController;
use App\Controllers\Admin\ParametresController;
use App\Controllers\Admin\StatsController;
use App\Controllers\Workspace\AvisAdminController;
use App\Controllers\Workspace\DocumentController;
use App\Controllers\Workspace\EmployeController;
use App\Controllers\Workspace\HoraireController;
use App\Controllers\Workspace\MenuAdminController;
use App\Controllers\Workspace\NotificationController;
use App\Controllers\Workspace\RecetteController;

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

require_once __DIR__ . '/../src/helpers.php';

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';

// Auto-migrate : applique les migrations manquantes au démarrage (idempotent, fail-silent)
Migrator::run();

// Healthcheck — réponse avant tout dispatch applicatif
if ($uri === '/health') {
    header('Content-Type: application/json; charset=UTF-8');
    $dbStatus = 'unknown';
    try {
        Database::getConnection()->query('SELECT 1');
        $dbStatus = 'ok';
    } catch (\Throwable) {
        $dbStatus = 'down';
    }
    // Toujours 200 — Railway ne doit pas tuer le container si la DB est lente au cold start
    echo json_encode(['status' => 'ok', 'db' => $dbStatus, 'ts' => time()]);
    exit;
}
// Suspension instance SaaS — bloque toutes les routes sauf déconnexion et health
if (!in_array($uri, ['/deconnexion', '/connexion'], true)) {
    try {
        if (\App\Config\PlanConfig::isSuspended()) {
            http_response_code(503);
            view('pages/suspended');
            exit;
        }
    } catch (\Throwable) {
        // Fail-open : ne pas bloquer si site_config indisponible
    }
}

$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    'GET'  => [
        '/'                    => [HomeController::class,    'index'],
        '/cron/rappels'        => [CronController::class,    'rappels'],
        '/devis/accepter'      => [DevisController::class,   'accepter'],
        '/menus'               => [MenuController::class,    'index'],
        '/menus/detail'        => [MenuController::class,    'detail'],
        '/livraison/calcul'    => [CommandeController::class,'calculLivraison'],
        '/commande/disponibilite' => [CommandeController::class,'calculDisponibilite'],
        '/verifier-email'      => [AuthController::class,    'verifyEmail'],
        '/connexion'           => [AuthController::class,    'loginForm'],
        '/inscription'         => [AuthController::class,    'registerForm'],
        '/deconnexion'         => [AuthController::class,    'logout'],
        '/mot-de-passe-oublie' => [AuthController::class,    'forgotForm'],
        '/reinitialiser'       => [AuthController::class,    'resetForm'],
        '/contact'             => [ContactController::class, 'index'],
        '/mentions-legales'    => [PageController::class,    'mentions'],
        '/cgv'                 => [PageController::class,    'cgv'],
    ],
    'POST' => [
        '/devis/accepter'      => [DevisController::class,   'accepter'],
        '/connexion'           => [AuthController::class,    'login'],
        '/inscription'         => [AuthController::class,    'register'],
        '/mot-de-passe-oublie' => [AuthController::class,    'forgot'],
        '/reinitialiser'       => [AuthController::class,    'reset'],
        '/contact'             => [ContactController::class, 'send'],
        '/stripe/webhook'      => [StripeController::class,  'webhook'],
    ],
    'GET_AUTH' => [
        '/mon-compte'                    => [UserController::class,    'dashboard'],
        '/mon-compte/export-commandes'   => [UserController::class,    'exportCommandes'],
        '/panier'                        => [PanierController::class,  'view'],
        '/commande/suivi'                => [CommandeController::class,'suivi'],
        '/stripe/checkout'               => [StripeController::class,  'checkout'],
        '/stripe/success'                => [StripeController::class,  'success'],
        '/stripe/cancel'                 => [StripeController::class,  'cancel'],
    ],
    'POST_AUTH' => [
        '/mon-compte/modifier'           => [UserController::class,  'update'],
        '/mon-compte/supprimer'          => [UserController::class,  'deleteAccount'],
        '/panier/ajouter'      => [PanierController::class,  'add'],
        '/panier/retirer'      => [PanierController::class,  'remove'],
        '/panier/vider'        => [PanierController::class,  'clear'],
        '/commande'            => [CommandeController::class,'create'],
        '/commande/modifier'   => [CommandeController::class,'update'],
        '/commande/annuler'    => [CommandeController::class,'cancel'],
        '/avis'                => [AvisController::class,    'create'],
    ],
    'GET_EMPLOYE' => [
        '/employe'                        => [EmployeController::class,    'dashboard'],
        '/employe/commandes'              => [EmployeController::class,    'commandes'],
        '/employe/commandes/calendrier'   => [EmployeController::class,    'calendrierJson'],
        '/employe/menus'                  => [MenuAdminController::class,  'index'],
        '/employe/avis'                   => [AvisAdminController::class,  'index'],
        '/employe/horaires'               => [HoraireController::class,    'index'],
        '/employe/recettes'               => [RecetteController::class,   'index'],
        '/employe/document/edit'          => [DocumentController::class,   'edit'],
        '/employe/document/apercu'        => [DocumentController::class,   'preview'],
        '/employe/document/export'        => [DocumentController::class,   'export'],
        '/employe/document/pdf'           => [DocumentController::class,   'exportPdf'],
        '/employe/changer-mot-de-passe'   => [EmployeController::class,    'changePasswordForm'],
        '/employe/notifications'          => [NotificationController::class,'index'],
        '/employe/notifications/count'    => [NotificationController::class,'count'],
        '/employe/recherche'              => [EmployeController::class,     'recherche'],
    ],
    'GET_ADMIN' => [
        '/admin'                     => [DashboardController::class,  'index'],
        '/admin/employes'            => [EmployeAdminController::class,'index'],
        '/admin/stats'               => [StatsController::class,      'stats'],
        '/admin/stats/export'        => [StatsController::class,      'exportStats'],
        '/admin/comptabilite'        => [StatsController::class,      'comptabilite'],
        '/admin/comptabilite/export' => [StatsController::class,      'exportComptabilite'],
        '/admin/accueil'             => [ParametresController::class, 'accueil'],
        '/admin/parametres'          => [ParametresController::class, 'index'],
    ],
    'POST_EMPLOYE' => [
        '/employe/paiement/enregistrer'  => [PaiementController::class,   'enregistrer'],
        '/employe/paiement/supprimer'    => [PaiementController::class,   'supprimer'],
        '/employe/changer-mot-de-passe'  => [EmployeController::class,    'changePassword'],
        '/employe/notifications/lire'    => [NotificationController::class,'markRead'],
        '/employe/commande/statut'       => [EmployeController::class,    'updateStatut'],
        '/employe/document/creer'          => [DocumentController::class,   'create'],
        '/employe/document/modifier'       => [DocumentController::class,   'update'],
        '/employe/document/finaliser'      => [DocumentController::class,   'finalize'],
        '/employe/document/archiver'       => [DocumentController::class,   'archive'],
        '/employe/document/envoyer'        => [DocumentController::class,   'send'],
        '/employe/document/accepter-devis'  => [DocumentController::class,   'accepterDevis'],
        '/employe/document/refuser-devis'   => [DocumentController::class,   'refuserDevis'],
        '/employe/document/signer-devis'    => [DocumentController::class,   'envoyerSignature'],
        '/employe/menu/creer'            => [MenuAdminController::class,  'createMenu'],
        '/employe/menu/modifier'         => [MenuAdminController::class,  'updateMenu'],
        '/employe/menu/supprimer'        => [MenuAdminController::class,  'deleteMenu'],
        '/employe/avis/valider'          => [AvisAdminController::class,  'valider'],
        '/employe/avis/accueil'          => [AvisAdminController::class,  'toggleAccueil'],
        '/employe/avis/supprimer'        => [AvisAdminController::class,  'supprimer'],
        '/employe/horaires/modifier'      => [HoraireController::class,    'update'],
        '/employe/recette/sauvegarder'    => [RecetteController::class,   'saveRecette'],
        '/employe/ingredient/creer'       => [RecetteController::class,   'createIngredient'],
        '/employe/ingredient/modifier'    => [RecetteController::class,   'updateIngredient'],
        '/employe/ingredient/supprimer'   => [RecetteController::class,   'deleteIngredient'],
        '/employe/stock/mouvement/ajouter'=> [RecetteController::class,   'addMouvement'],
        '/employe/stock/mouvement/supprimer' => [RecetteController::class,'deleteMouvement'],
        '/employe/plat/creer'            => [MenuAdminController::class,  'createPlat'],
        '/employe/plat/modifier'         => [MenuAdminController::class,  'updatePlat'],
        '/employe/plat/supprimer'        => [MenuAdminController::class,  'deletePlat'],
        '/employe/menu/image/supprimer'  => [MenuAdminController::class,  'deleteMenuImage'],
    ],
    'POST_ADMIN' => [
        '/admin/employe/creer'       => [EmployeAdminController::class, 'create'],
        '/admin/employe/desactiver'  => [EmployeAdminController::class, 'disable'],
        '/admin/employe/supprimer'   => [EmployeAdminController::class, 'delete'],
        '/admin/accueil/modifier'    => [ParametresController::class,   'updateAccueil'],
        '/admin/parametres/modifier' => [ParametresController::class,   'update'],
        '/admin/taux-tva/creer'      => [ParametresController::class,   'createTauxTva'],
        '/admin/taux-tva/toggle'     => [ParametresController::class,   'toggleTauxTva'],
        '/admin/taux-tva/defaut'     => [ParametresController::class,   'setDefaultTauxTva'],
    ],
];

function resolveRoute(array $routes, string $method, string $uri): ?array {
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
