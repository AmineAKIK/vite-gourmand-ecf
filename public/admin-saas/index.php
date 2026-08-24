<?php
/**
 * AkikSystems — Dashboard SaaS Tugères.
 *
 * Authentication is intentionally isolated from the customer/back-office session:
 * the master secret is submitted only on login and is never persisted client-side.
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/src/Config/config.php';

use App\Config\Database;
use App\Config\PlanConfig;
use App\Security\RateLimiter;
use App\Security\RateLimitUnavailableException;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, max-age=0');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

session_name('TUGERES_SAAS');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/admin-saas',
    'domain' => '',
    'secure' => (APP_ENV !== 'development'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$secret = SAAS_SECRET;
if (!$secret) {
    http_response_code(503);
    exit('SAAS_SECRET non configuré.');
}

$now = time();
$authenticatedAt = (int) ($_SESSION['saas_authenticated_at'] ?? 0);
$lastActivity = (int) ($_SESSION['saas_last_activity'] ?? 0);
if (
    !empty($_SESSION['saas_authenticated'])
    && (
        $authenticatedAt <= 0
        || ($now - $authenticatedAt) > 3600
        || ($lastActivity > 0 && ($now - $lastActivity) > 1800)
    )
) {
    clearSaasAuth();
}

$loginError = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

if (empty($_SESSION['saas_authenticated'])) {
    if ($method === 'POST' && $action === 'login') {
        $ip = RateLimiter::clientIp();
        try {
            RateLimiter::check($ip, 'saas_login', 5, 900);
        } catch (RateLimitUnavailableException $e) {
            http_response_code(503);
            $loginError = $e->getMessage();
        } catch (\RuntimeException $e) {
            http_response_code(429);
            $loginError = $e->getMessage();
        }

        if ($loginError === '') {
            $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
            if ($token !== '' && hash_equals($secret, $token)) {
                RateLimiter::reset($ip, 'saas_login');
                session_regenerate_id(true);
                $_SESSION['saas_authenticated'] = true;
                $_SESSION['saas_authenticated_at'] = $now;
                $_SESSION['saas_last_activity'] = $now;
                $_SESSION['saas_csrf'] = bin2hex(random_bytes(32));
                header('Location: /admin-saas/');
                exit;
            }

            RateLimiter::record($ip, 'saas_login');
            $loginError = 'Identifiants invalides.';
        }
    }

    showLoginForm($loginError);
    exit;
}

$_SESSION['saas_last_activity'] = $now;
$csrf = is_string($_SESSION['saas_csrf'] ?? null) ? $_SESSION['saas_csrf'] : '';
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['saas_csrf'] = $csrf;
}

$message = '';
$msgType = 'success';

if ($method === 'POST' && $action !== '' && $action !== 'login') {
    $postedCsrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if ($postedCsrf === '' || !hash_equals($csrf, $postedCsrf)) {
        http_response_code(403);
        exit('Requête refusée.');
    }

    if ($action === 'logout') {
        clearSaasAuth();
        session_regenerate_id(true);
        header('Location: /admin-saas/');
        exit;
    }

    $db = Database::getConnection();
    if ($action === 'set_plan') {
        $plan = is_string($_POST['plan'] ?? null) ? $_POST['plan'] : '';
        if (in_array($plan, ['starter', 'pro', 'premium'], true)) {
            $db->prepare("INSERT INTO site_config (cle, valeur) VALUES ('plan', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
                ->execute([$plan]);
            $message = 'Plan mis à jour : ' . $plan;
        } else {
            $message = 'Plan invalide.';
            $msgType = 'warning';
        }
    } elseif ($action === 'suspend') {
        $val = ($_POST['suspend'] ?? '0') === '1' ? '1' : '0';
        $db->prepare("INSERT INTO site_config (cle, valeur) VALUES ('plan_suspendu', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
            ->execute([$val]);
        $message = $val === '1' ? 'Instance suspendue.' : 'Instance réactivée.';
    } elseif ($action === 'set_license') {
        // La cryptographie de licence reste volontairement inchangée ici : PR18 la remplace.
        $licKey = trim(is_string($_POST['lic_key'] ?? null) ? $_POST['lic_key'] : '');
        $domainRaw = is_string($_POST['lic_domain'] ?? null) ? $_POST['lic_domain'] : '';
        $licDomain = strtolower(trim((string) preg_replace('#^https?://#', '', $domainRaw), '/'));
        if ($licKey !== '' && $licDomain !== '') {
            $licSecret = 'tugeres_akiksystems_2025_' . $licKey;
            $licHash = hash_hmac('sha256', $licDomain, $licSecret);
            foreach ([
                ['license_key', $licKey],
                ['license_domain', $licDomain],
                ['license_hash', $licHash],
            ] as [$key, $value]) {
                $db->prepare('INSERT INTO site_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
                    ->execute([$key, $value]);
            }
            $message = 'Licence activée pour ' . $licDomain . '.';
        } else {
            $message = 'Domaine et clé obligatoires.';
            $msgType = 'warning';
        }
    }
}

$config = [];
$dbError = '';
try {
    $db = Database::getConnection();
    foreach ($db->query('SELECT cle, valeur FROM site_config')->fetchAll() as $row) {
        $config[$row['cle']] = $row['valeur'];
    }

    $totalCommandes = (int) $db->query('SELECT COUNT(*) FROM commande')->fetchColumn();
    $cmdCeMois = (int) $db->query("SELECT COUNT(*) FROM commande WHERE date_commande >= DATE_FORMAT(NOW(),'%Y-%m-01') AND statut != 'annulee'")->fetchColumn();
    $totalUsers = (int) $db->query('SELECT COUNT(*) FROM utilisateur WHERE role_id = 1 AND actif = 1')->fetchColumn();
    $totalEmployes = (int) $db->query('SELECT COUNT(*) FROM utilisateur WHERE role_id = 2 AND actif = 1')->fetchColumn();
    $caTotalRaw = $db->query("SELECT COALESCE(SUM(prix_total),0) FROM commande WHERE statut NOT IN ('annulee')")->fetchColumn();
    $caTotal = number_format((float) $caTotalRaw, 2, ',', ' ');
    $derniereCommande = $db->query('SELECT date_commande FROM commande ORDER BY commande_id DESC LIMIT 1')->fetchColumn();
} catch (\Throwable $e) {
    error_log('[admin-saas] lecture dashboard impossible: ' . $e->getMessage());
    $dbError = 'Données temporairement indisponibles.';
}

$plan = is_string($config['plan'] ?? null) ? $config['plan'] : 'premium';
if (!in_array($plan, ['starter', 'pro', 'premium'], true)) {
    $plan = 'premium';
}
$suspendu = ($config['plan_suspendu'] ?? '0') === '1';
$siteName = is_string($config['site_nom'] ?? null) ? $config['site_nom'] : '(non configuré)';
$siteDom = is_string($config['site_domaine'] ?? null) ? $config['site_domaine'] : (string) ($_SERVER['HTTP_HOST'] ?? '');
$licKey = is_string($config['license_key'] ?? null) ? $config['license_key'] : '';
$licDom = is_string($config['license_domain'] ?? null) ? $config['license_domain'] : '';
$planDef = PlanConfig::definition($plan) ?? [];
$maxCmd = (int) ($planDef['max_commandes_mois'] ?? 0);
$maxEmp = (int) ($planDef['max_employes'] ?? 0);
$features = is_array($planDef['features'] ?? null) ? $planDef['features'] : [];

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clearSaasAuth(): void
{
    unset(
        $_SESSION['saas_authenticated'],
        $_SESSION['saas_authenticated_at'],
        $_SESSION['saas_last_activity'],
        $_SESSION['saas_csrf']
    );
}

function showLoginForm(string $error = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AkikSystems — Accès dashboard</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f0f1a;color:#e5e7eb;min-height:100vh;display:flex;align-items:center;justify-content:center}.card{background:#1a1a2e;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:2.5rem 2rem;width:340px}h1{font-size:1.1rem;color:#a78bfa;margin-bottom:.25rem}p{font-size:.82rem;color:#6b7280;margin-bottom:1.5rem}label{display:block;font-size:.8rem;color:#9ca3af;margin-bottom:.3rem}input{width:100%;background:#0f0f1a;border:1px solid rgba(255,255,255,.15);border-radius:6px;color:#e5e7eb;padding:.6rem .9rem;font-size:.9rem;margin-bottom:1rem}.btn{width:100%;padding:.65rem;background:#7c3aed;color:#fff;border:0;border-radius:6px;font-weight:600;cursor:pointer}.err{font-size:.8rem;color:#f87171;margin-bottom:.75rem}
</style>
</head>
<body><div class="card"><h1>AkikSystems</h1><p>Dashboard SaaS Tugères — accès restreint</p>
<?php if ($error !== ''): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
<form method="POST" action="/admin-saas/">
<input type="hidden" name="action" value="login">
<label>Secret d'accès</label><input type="password" name="token" autofocus autocomplete="current-password" required>
<button type="submit" class="btn">Accéder</button>
</form></div></body></html>
    <?php
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AkikSystems — Dashboard SaaS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f0f1a;color:#e5e7eb;min-height:100vh}.topbar{background:#1a1a2e;border-bottom:1px solid rgba(255,255,255,.08);padding:.75rem 1.5rem;display:flex;align-items:center;justify-content:space-between}.topbar-brand{font-weight:700;color:#a78bfa}.topbar-meta{font-size:.78rem;color:#6b7280;display:flex;align-items:center;gap:.75rem}.main{padding:1.5rem;max-width:1200px;margin:0 auto}h1{font-size:1.25rem;margin-bottom:1.5rem}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:2rem}.kpi,.card{background:#1a1a2e;border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:1.25rem}.card{margin-bottom:1.5rem}.kpi-val{font-size:1.65rem;font-weight:700;color:#a78bfa}.kpi-label,label{font-size:.78rem;color:#9ca3af}.card h2{font-size:.9rem;text-transform:uppercase;color:#6b7280;margin-bottom:1rem}.row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}input[type=text],select{width:100%;background:#0f0f1a;border:1px solid rgba(255,255,255,.15);border-radius:6px;color:#e5e7eb;padding:.5rem .75rem}.btn{display:inline-flex;padding:.5rem 1rem;border:0;border-radius:6px;font-weight:600;cursor:pointer}.btn-purple{background:#7c3aed;color:#fff}.btn-red{background:#dc2626;color:#fff}.btn-green{background:#16a34a;color:#fff}.btn-link{background:none;color:#9ca3af}.badge{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700}.badge-starter{background:#1e3a5f;color:#60a5fa}.badge-pro{background:#1e3a5f;color:#34d399}.badge-premium{background:#3b1f6e;color:#a78bfa}.badge-ok{background:#14532d;color:#4ade80}.badge-suspended{background:#7f1d1d;color:#f87171}.alert{padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem}.alert-success{background:#14532d;color:#4ade80}.alert-warning{background:#713f12;color:#fbbf24}.info-row{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.06)}.info-label{color:#9ca3af}@media(max-width:700px){.row2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="topbar"><div class="topbar-brand">AkikSystems — Dashboard SaaS</div><div class="topbar-meta">
<span><?= e($siteName) ?></span><span class="badge badge-<?= e($plan) ?>"><?= e(ucfirst($plan)) ?></span>
<?php if ($suspendu): ?><span class="badge badge-suspended">SUSPENDU</span><?php endif; ?>
<form method="POST" action="/admin-saas/"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit" class="btn btn-link">Déconnexion</button></form>
</div></div>
<div class="main">
<?php if ($message !== ''): ?><div class="alert alert-<?= e($msgType) ?>"><?= e($message) ?></div><?php endif; ?>
<?php if ($dbError !== ''): ?><div class="alert alert-warning"><?= e($dbError) ?></div><?php endif; ?>
<h1>Instance : <?= e($siteName) ?></h1>
<div class="grid">
<div class="kpi"><div class="kpi-val"><?= e($totalCommandes ?? '—') ?></div><div class="kpi-label">Commandes totales</div></div>
<div class="kpi"><div class="kpi-val"><?= e($cmdCeMois ?? '—') ?></div><div class="kpi-label">Commandes ce mois</div></div>
<div class="kpi"><div class="kpi-val"><?= e($caTotal ?? '—') ?> €</div><div class="kpi-label">CA total</div></div>
<div class="kpi"><div class="kpi-val"><?= e($totalUsers ?? '—') ?></div><div class="kpi-label">Clients actifs</div></div>
<div class="kpi"><div class="kpi-val"><?= e($totalEmployes ?? '—') ?></div><div class="kpi-label">Employés actifs</div></div>
</div>
<div class="card"><h2>État de l'instance</h2>
<div class="info-row"><span class="info-label">Nom</span><span><?= e($siteName) ?></span></div>
<div class="info-row"><span class="info-label">Domaine</span><span><?= e($siteDom) ?></span></div>
<div class="info-row"><span class="info-label">Dernière commande</span><span><?= e($derniereCommande ?? '—') ?></span></div>
<div class="info-row"><span class="info-label">Licence domaine</span><span><?= e($licDom ?: '(non activée)') ?></span></div>
</div>
<div class="row2">
<div class="card"><h2>Changer le plan</h2><form method="POST" action="/admin-saas/"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="set_plan"><label>Plan</label><select name="plan"><?php foreach (['starter'=>'Starter','pro'=>'Pro','premium'=>'Premium'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $plan === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><br><br><button class="btn btn-purple" type="submit">Appliquer</button></form></div>
<div class="card"><h2>Suspension</h2><form method="POST" action="/admin-saas/"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="suspend"><input type="hidden" name="suspend" value="<?= $suspendu ? '0' : '1' ?>"><button class="btn <?= $suspendu ? 'btn-green' : 'btn-red' ?>" type="submit"><?= $suspendu ? 'Réactiver' : 'Suspendre' ?></button></form></div>
</div>
<div class="card"><h2>Gestion de la licence</h2><form method="POST" action="/admin-saas/"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="set_license"><div class="row2"><div><label>Domaine</label><input type="text" name="lic_domain" value="<?= e($licDom) ?>"></div><div><label>Clé</label><input type="text" name="lic_key" value="<?= e($licKey) ?>"></div></div><br><button class="btn btn-purple" type="submit">Activer la licence</button></form></div>
<div class="card"><h2>Quotas du plan</h2>
<div class="info-row"><span class="info-label">Commandes / mois</span><span><?= $maxCmd === 0 ? 'Illimité' : e(($cmdCeMois ?? 0) . ' / ' . $maxCmd) ?></span></div>
<div class="info-row"><span class="info-label">Employés max</span><span><?= $maxEmp === 0 ? 'Illimité' : e(($totalEmployes ?? 0) . ' / ' . $maxEmp) ?></span></div>
<?php foreach ($features as $feature => $enabled): ?><div class="info-row"><span class="info-label"><?= e(str_replace('_', ' ', (string) $feature)) ?></span><span><?= $enabled ? '<span class="badge badge-ok">✓</span>' : '✗' ?></span></div><?php endforeach; ?>
</div>
</div>
</body>
</html>
