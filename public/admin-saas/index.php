<?php
/**
 * AkikSystems — Dashboard SaaS Tugères
 *
 * Accessible sur : https://montraiteur.fr/admin-saas/?token=SAAS_SECRET
 *
 * Protégé par SAAS_SECRET (variable d'environnement).
 * Affiche : plan actuel, quotas, activité, outils de gestion de l'instance.
 *
 * NE PAS exposer ce fichier sans SAAS_SECRET configuré.
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/src/Config/config.php';

// ── Authentification par token secret ────────────────────────────────────────
$secret = SAAS_SECRET;
if (!$secret) {
    http_response_code(503);
    die('SAAS_SECRET non configuré. Ajouter SAAS_SECRET dans les variables d\'environnement.');
}

$tokenIn = $_GET['token'] ?? $_POST['token'] ?? $_COOKIE['saas_token'] ?? '';
if (!hash_equals($secret, $tokenIn)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token_submit'])) {
        // Mauvais token soumis
        $loginError = true;
    } else {
        // Afficher le formulaire de login
        showLoginForm(isset($loginError));
        exit;
    }
}

// Token valide — poser un cookie de session (1h)
if (!isset($_COOKIE['saas_token'])) {
    setcookie('saas_token', $secret, time() + 3600, '/admin-saas', '', true, true);
}

// ── Actions POST ──────────────────────────────────────────────────────────────
$action  = $_POST['action'] ?? '';
$message = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    // CSRF minimal : vérifier que le token est valide (déjà fait ci-dessus)
    $db = \App\Config\Database::getConnection();

    if ($action === 'set_plan') {
        $plan = $_POST['plan'] ?? '';
        if (in_array($plan, ['starter', 'pro', 'premium'], true)) {
            $db->prepare("INSERT INTO site_config (cle, valeur) VALUES ('plan', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
               ->execute([$plan]);
            $message = "Plan mis à jour : $plan";
        }
    } elseif ($action === 'suspend') {
        $val = ($_POST['suspend'] ?? '0') === '1' ? '1' : '0';
        $db->prepare("INSERT INTO site_config (cle, valeur) VALUES ('plan_suspendu', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
           ->execute([$val]);
        $message = $val === '1' ? 'Instance suspendue.' : 'Instance réactivée.';
    } elseif ($action === 'set_license') {
        $licKey    = trim($_POST['lic_key']    ?? '');
        $licDomain = strtolower(trim(preg_replace('#^https?://#', '', $_POST['lic_domain'] ?? ''), '/'));
        if ($licKey && $licDomain) {
            $licSecret = 'tugeres_akiksystems_2025_' . $licKey;
            $licHash   = hash_hmac('sha256', $licDomain, $licSecret);
            foreach ([
                ['license_key', $licKey],
                ['license_domain', $licDomain],
                ['license_hash', $licHash],
            ] as [$k, $v]) {
                $db->prepare("INSERT INTO site_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
                   ->execute([$k, $v]);
            }
            $message = "Licence activée pour $licDomain.";
        } else {
            $message = 'Domaine et clé obligatoires.';
            $msgType = 'warning';
        }
    }
}

// ── Lecture des données ───────────────────────────────────────────────────────
try {
    $db = \App\Config\Database::getConnection();

    // Config générale
    $config = [];
    foreach ($db->query("SELECT cle, valeur FROM site_config")->fetchAll() as $row) {
        $config[$row['cle']] = $row['valeur'];
    }

    // KPIs
    $totalCommandes    = (int)$db->query("SELECT COUNT(*) FROM commande")->fetchColumn();
    $cmdCeMois         = (int)$db->query("SELECT COUNT(*) FROM commande WHERE date_commande >= DATE_FORMAT(NOW(),'%Y-%m-01') AND statut != 'annulee'")->fetchColumn();
    $totalUsers        = (int)$db->query("SELECT COUNT(*) FROM utilisateur WHERE role_id = 1 AND actif = 1")->fetchColumn();
    $totalEmployes     = (int)$db->query("SELECT COUNT(*) FROM utilisateur WHERE role_id = 2 AND actif = 1")->fetchColumn();
    $caTotalRaw        = $db->query("SELECT COALESCE(SUM(prix_total),0) FROM commande WHERE statut NOT IN ('annulee')")->fetchColumn();
    $caTotal           = number_format((float)$caTotalRaw, 2, ',', ' ');
    $derniereCommande  = $db->query("SELECT date_commande FROM commande ORDER BY commande_id DESC LIMIT 1")->fetchColumn();
    $dernierLogin      = $db->query("SELECT last_activity FROM utilisateur ORDER BY last_activity DESC LIMIT 1")->fetchColumn() ?? null;

} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

$plan      = $config['plan']            ?? 'premium';
$suspendu  = ($config['plan_suspendu'] ?? '0') === '1';
$siteName  = $config['site_nom']        ?? '(non configuré)';
$siteDom   = $config['site_domaine']    ?? $_SERVER['HTTP_HOST'] ?? '';
$licKey    = $config['license_key']     ?? '';
$licDom    = $config['license_domain']  ?? '';

// ── Rendu ─────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AkikSystems — Dashboard SaaS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f0f1a;color:#e5e7eb;min-height:100vh}
.topbar{background:#1a1a2e;border-bottom:1px solid rgba(255,255,255,.08);padding:.75rem 1.5rem;display:flex;align-items:center;justify-content:space-between}
.topbar-brand{font-weight:700;color:#a78bfa;font-size:1rem}
.topbar-meta{font-size:.78rem;color:#6b7280}
.main{padding:1.5rem;max-width:1200px;margin:0 auto}
h1{font-size:1.25rem;font-weight:700;margin-bottom:1.5rem;color:#f3f4f6}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2rem}
.kpi{background:#1a1a2e;border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:1.25rem}
.kpi-val{font-size:1.75rem;font-weight:700;color:#a78bfa}
.kpi-label{font-size:.78rem;color:#9ca3af;margin-top:.25rem}
.card{background:#1a1a2e;border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:1.5rem;margin-bottom:1.5rem}
.card h2{font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:1.25rem}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
label{display:block;font-size:.8rem;color:#9ca3af;margin-bottom:.3rem}
input[type=text],select{width:100%;background:#0f0f1a;border:1px solid rgba(255,255,255,.15);border-radius:6px;color:#e5e7eb;padding:.5rem .75rem;font-size:.85rem}
input:focus,select:focus{outline:none;border-color:#a78bfa}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1.25rem;border:none;border-radius:6px;font-size:.85rem;font-weight:600;cursor:pointer;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-purple{background:#7c3aed;color:#fff}
.btn-red{background:#dc2626;color:#fff}
.btn-green{background:#16a34a;color:#fff}
.btn-sm{padding:.35rem .85rem;font-size:.78rem}
.badge{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700}
.badge-starter{background:#1e3a5f;color:#60a5fa}
.badge-pro{background:#1e3a5f;color:#34d399}
.badge-premium{background:#3b1f6e;color:#a78bfa}
.badge-ok{background:#14532d;color:#4ade80}
.badge-suspended{background:#7f1d1d;color:#f87171}
.alert{padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.85rem}
.alert-success{background:#14532d;color:#4ade80;border:1px solid #166534}
.alert-warning{background:#713f12;color:#fbbf24;border:1px solid #92400e}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:.85rem}
.info-row:last-child{border-bottom:none}
.info-label{color:#9ca3af}
.info-val{color:#e5e7eb;font-weight:500}
.logout{font-size:.78rem;color:#6b7280;text-decoration:none}
.logout:hover{color:#e5e7eb}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">AkikSystems — Dashboard SaaS</div>
    <div class="topbar-meta">
        <?= htmlspecialchars($siteName) ?> &middot;
        <span class="badge badge-<?= htmlspecialchars($plan) ?>"><?= htmlspecialchars(ucfirst($plan)) ?></span>
        <?php if ($suspendu): ?><span class="badge badge-suspended ms-1">SUSPENDU</span><?php endif; ?>
        &nbsp;&nbsp;
        <a href="?logout=1" class="logout">Déconnexion</a>
    </div>
</div>

<?php
// Logout
if (isset($_GET['logout'])) {
    setcookie('saas_token', '', time() - 3600, '/admin-saas', '', true, true);
    header('Location: /admin-saas/');
    exit;
}
?>

<div class="main">

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($dbError)): ?>
<div class="alert alert-warning">Erreur DB : <?= htmlspecialchars($dbError) ?></div>
<?php endif; ?>

<h1>Instance : <?= htmlspecialchars($siteName) ?></h1>

<!-- KPIs -->
<div class="grid">
    <div class="kpi">
        <div class="kpi-val"><?= $totalCommandes ?? '—' ?></div>
        <div class="kpi-label">Commandes totales</div>
    </div>
    <div class="kpi">
        <div class="kpi-val"><?= $cmdCeMois ?? '—' ?></div>
        <div class="kpi-label">Commandes ce mois</div>
    </div>
    <div class="kpi">
        <div class="kpi-val"><?= $caTotal ?? '—' ?> €</div>
        <div class="kpi-label">CA total</div>
    </div>
    <div class="kpi">
        <div class="kpi-val"><?= $totalUsers ?? '—' ?></div>
        <div class="kpi-label">Clients actifs</div>
    </div>
    <div class="kpi">
        <div class="kpi-val"><?= $totalEmployes ?? '—' ?></div>
        <div class="kpi-label">Employés actifs</div>
    </div>
</div>

<!-- Infos instance -->
<div class="card">
    <h2>État de l'instance</h2>
    <div class="info-row"><span class="info-label">Nom</span><span class="info-val"><?= htmlspecialchars($siteName) ?></span></div>
    <div class="info-row"><span class="info-label">Domaine</span><span class="info-val"><?= htmlspecialchars($siteDom) ?></span></div>
    <div class="info-row"><span class="info-label">Plan</span><span class="info-val"><span class="badge badge-<?= htmlspecialchars($plan) ?>"><?= htmlspecialchars(ucfirst($plan)) ?></span></span></div>
    <div class="info-row"><span class="info-label">Statut</span><span class="info-val"><?= $suspendu ? '<span class="badge badge-suspended">SUSPENDU</span>' : '<span class="badge badge-ok">ACTIF</span>' ?></span></div>
    <div class="info-row"><span class="info-label">Dernière commande</span><span class="info-val"><?= htmlspecialchars($derniereCommande ?? '—') ?></span></div>
    <div class="info-row"><span class="info-label">Licence domaine</span><span class="info-val"><?= htmlspecialchars($licDom ?: '(non activée)') ?></span></div>
    <div class="info-row"><span class="info-label">Version app</span><span class="info-val"><?= APP_VERSION ?></span></div>
</div>

<!-- Actions -->
<div class="row2">

    <!-- Changer de plan -->
    <div class="card">
        <h2>Changer le plan</h2>
        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($secret) ?>">
            <input type="hidden" name="action" value="set_plan">
            <div style="margin-bottom:1rem">
                <label>Plan</label>
                <select name="plan">
                    <?php foreach (['starter' => 'Starter (29€/mois)', 'pro' => 'Pro (59€/mois)', 'premium' => 'Premium (99€/mois)'] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $plan === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-purple btn-sm">Appliquer</button>
        </form>
    </div>

    <!-- Suspension -->
    <div class="card">
        <h2>Suspension de l'instance</h2>
        <p style="font-size:.82rem;color:#9ca3af;margin-bottom:1rem">
            Suspendre bloque tous les accès sauf /connexion et /deconnexion.
        </p>
        <form method="POST" style="display:flex;gap:.75rem;flex-wrap:wrap">
            <input type="hidden" name="token" value="<?= htmlspecialchars($secret) ?>">
            <input type="hidden" name="action" value="suspend">
            <?php if ($suspendu): ?>
                <input type="hidden" name="suspend" value="0">
                <button type="submit" class="btn btn-green btn-sm">Réactiver l'instance</button>
            <?php else: ?>
                <input type="hidden" name="suspend" value="1">
                <button type="submit" class="btn btn-red btn-sm"
                    onclick="return confirm('Confirmer la suspension ?')">Suspendre l'instance</button>
            <?php endif; ?>
        </form>
    </div>

</div>

<!-- Licence -->
<div class="card">
    <h2>Gestion de la licence</h2>
    <?php if ($licKey && $licDom): ?>
    <div class="info-row" style="margin-bottom:1rem">
        <span class="info-label">Clé active</span>
        <span class="info-val"><?= htmlspecialchars($licKey) ?> — <?= htmlspecialchars($licDom) ?></span>
    </div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($secret) ?>">
        <input type="hidden" name="action" value="set_license">
        <div class="row2" style="margin-bottom:1rem">
            <div>
                <label>Domaine</label>
                <input type="text" name="lic_domain" value="<?= htmlspecialchars($licDom) ?>" placeholder="montraiteur.fr">
            </div>
            <div>
                <label>Clé de licence</label>
                <input type="text" name="lic_key" value="<?= htmlspecialchars($licKey) ?>" placeholder="XXXX-XXXX-XXXX">
            </div>
        </div>
        <button type="submit" class="btn btn-purple btn-sm">Activer la licence</button>
    </form>
</div>

<!-- Quotas plan -->
<div class="card">
    <h2>Quotas du plan actuel</h2>
    <?php
    $planDef = \App\Config\PlanConfig::definition($plan) ?? [];
    $maxCmd  = $planDef['max_commandes_mois'] ?? 0;
    $maxEmp  = $planDef['max_employes'] ?? 0;
    $features = $planDef['features'] ?? [];
    ?>
    <div class="info-row">
        <span class="info-label">Commandes / mois</span>
        <span class="info-val">
            <?php if ($maxCmd === 0): ?>Illimité
            <?php else: ?><?= ($cmdCeMois ?? 0) ?> / <?= $maxCmd ?>
                <?php if (($cmdCeMois ?? 0) >= $maxCmd): ?><span style="color:#f87171"> (QUOTA ATTEINT)</span><?php endif; ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="info-row">
        <span class="info-label">Employés max</span>
        <span class="info-val"><?= $maxEmp === 0 ? 'Illimité' : ($totalEmployes ?? 0) . ' / ' . $maxEmp ?></span>
    </div>
    <?php foreach ($features as $feat => $enabled): ?>
    <div class="info-row">
        <span class="info-label"><?= htmlspecialchars(str_replace('_', ' ', $feat)) ?></span>
        <span class="info-val"><?= $enabled ? '<span class="badge badge-ok">✓</span>' : '<span style="color:#6b7280">✗</span>' ?></span>
    </div>
    <?php endforeach; ?>
</div>

<p style="text-align:center;font-size:.72rem;color:#374151;margin-top:2rem">
    AkikSystems · Tugères <?= APP_VERSION ?> · Dashboard SaaS
</p>

</div>
</body>
</html>
<?php

// ─────────────────────────────────────────────────────────────────────────────
// Helper : formulaire de login
// ─────────────────────────────────────────────────────────────────────────────
function showLoginForm(bool $error = false): void {
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AkikSystems — Accès dashboard</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f0f1a;color:#e5e7eb;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#1a1a2e;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:2.5rem 2rem;width:340px}
h1{font-size:1.1rem;font-weight:700;color:#a78bfa;margin-bottom:.25rem}
p{font-size:.82rem;color:#6b7280;margin-bottom:1.5rem}
label{display:block;font-size:.8rem;color:#9ca3af;margin-bottom:.3rem}
input[type=password]{width:100%;background:#0f0f1a;border:1px solid rgba(255,255,255,.15);border-radius:6px;color:#e5e7eb;padding:.6rem .9rem;font-size:.9rem;margin-bottom:1rem}
input:focus{outline:none;border-color:#a78bfa}
.btn{width:100%;padding:.65rem;background:#7c3aed;color:#fff;border:none;border-radius:6px;font-size:.9rem;font-weight:600;cursor:pointer}
.err{font-size:.8rem;color:#f87171;margin-bottom:.75rem}
</style>
</head>
<body>
<div class="card">
<h1>AkikSystems</h1>
<p>Dashboard SaaS Tugères — accès restreint</p>
<?php if ($error): ?>
<div class="err">Token invalide.</div>
<?php endif; ?>
<form method="POST">
    <input type="hidden" name="token_submit" value="1">
    <label>Token d'accès</label>
    <input type="password" name="token" autofocus autocomplete="current-password">
    <button type="submit" class="btn">Accéder</button>
</form>
</div>
</body>
</html>
    <?php
}
