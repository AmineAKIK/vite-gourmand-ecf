<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tugères — Installation</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f4f5;color:#1a1a2e;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.10);padding:2.5rem 2rem;width:100%;max-width:640px}
h1{font-size:1.5rem;font-weight:700;color:#8B1A2B;margin-bottom:.25rem}
.subtitle{color:#6b7280;font-size:.9rem;margin-bottom:2rem}
.step-bar{display:flex;gap:.5rem;margin-bottom:2rem}
.step{flex:1;height:4px;border-radius:2px;background:#e5e7eb}
.step.done{background:#8B1A2B}.step.current{background:#c9475b}
.section h2{font-size:1rem;font-weight:700;margin-bottom:1.25rem;padding-bottom:.5rem;border-bottom:2px solid #f0f0f0}
.form-group{margin-bottom:1.25rem}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.35rem;color:#374151}
label .req{color:#dc2626}
input[type=text],input[type=email],input[type=password],input[type=url]{width:100%;padding:.65rem .9rem;border:1px solid #d1d5db;border-radius:6px;font-size:.9rem;transition:border-color .2s}
input:focus{outline:none;border-color:#8B1A2B;box-shadow:0 0 0 3px rgba(139,26,43,.1)}
.hint{font-size:.75rem;color:#9ca3af;margin-top:.35rem}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.btn{display:inline-block;padding:.75rem 2rem;background:#8B1A2B;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;transition:background .2s}
.btn:hover{background:#6b1420}
.btn-full{width:100%}
.alert{padding:1rem 1.25rem;border-radius:8px;margin-bottom:1.5rem;font-size:.88rem}
.alert-err{background:#fee2e2;color:#991b1b;border-left:4px solid #dc2626}
.alert-ok{background:#dcfce7;color:#166534;border-left:4px solid #16a34a}
.log{background:#1a1a2e;color:#e5e7eb;border-radius:8px;padding:1.25rem;font-family:monospace;font-size:.8rem;max-height:360px;overflow-y:auto;margin-bottom:1.5rem;white-space:pre-wrap}
.log .ok{color:#4ade80}.log .info{color:#60a5fa}.log .warn{color:#fbbf24}.log .fail{color:#f87171}
.check-list{list-style:none;font-size:.88rem}
.check-list li{padding:.35rem 0;display:flex;gap:.5rem;align-items:flex-start}
.check-list .ico-ok{color:#16a34a}.check-list .ico-fail{color:#dc2626}
.divider{border:none;border-top:1px solid #f0f0f0;margin:1.5rem 0}
.big-ok{text-align:center;padding:2rem 0}
.big-ok .icon{font-size:3rem;display:block;margin-bottom:.75rem}
.big-ok h2{font-size:1.25rem;font-weight:700;color:#166534;margin-bottom:.5rem}
.big-ok p{color:#6b7280;font-size:.9rem;margin-bottom.25rem}
.links{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:1.5rem}
.link-card{border:1px solid #e5e7eb;border-radius:8px;padding:.9rem 1rem;text-decoration:none;color:inherit;transition:border-color .2s}
.link-card:hover{border-color:#8B1A2B}
.link-card .lc-title{font-weight:600;font-size:.88rem;color:#8B1A2B}
.link-card .lc-desc{font-size:.78rem;color:#6b7280;margin-top:.15rem}
</style>
</head>
<body>
<div class="card">
<h1>Tugères</h1>
<p class="subtitle">Assistant d'installation guidée</p>

<?php
$root       = dirname(__DIR__);
$sqlDir     = $root . '/sql';
$migrations = $sqlDir . '/migrations';
$lockFile   = $root . '/public/uploads/.installed';

// ── Garde — déjà installé ──────────────────────────────────────────────────
if (file_exists($lockFile) && !isset($_GET['force'])) {
    ?>
    <div class="alert alert-ok">
        ✓ Tugères est déjà installé sur ce serveur.<br>
        Pour réinstaller, supprimez <code>public/uploads/.installed</code> et rechargez cette page.
    </div>
    <div class="links">
        <a href="/" class="link-card"><div class="lc-title">Accueil du site</div><div class="lc-desc">Page principale</div></a>
        <a href="/admin" class="link-card"><div class="lc-title">Espace admin</div><div class="lc-desc">Gérer les paramètres</div></a>
    </div>
    </div></body></html>
    <?php exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function logLine(string $cls, string $msg): void {
    echo '<span class="' . $cls . '">' . htmlspecialchars($msg) . "\n</span>";
    ob_flush(); flush();
}

// ── Parse .env ─────────────────────────────────────────────────────────────
$env = [];
if (file_exists($root . '/.env')) {
    foreach (file($root . '/.env') as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}
// Env vars also come from Railway / system environment
foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','BASE_URL','APP_ENV'] as $k) {
    if (!isset($env[$k]) && ($sv = getenv($k)) !== false) $env[$k] = $sv;
    if (!isset($env[$k]) && !empty($_ENV[$k])) $env[$k] = $_ENV[$k];
}

$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];

// ── STEP 2 — process form ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $adminEmail  = trim($_POST['admin_email']  ?? '');
    $adminPrenom = trim($_POST['admin_prenom'] ?? '');
    $adminNom    = trim($_POST['admin_nom']    ?? '');
    $adminPass1  = $_POST['admin_pass1'] ?? '';
    $adminPass2  = $_POST['admin_pass2'] ?? '';
    $licDomain   = trim($_POST['lic_domain'] ?? '');
    $licKey      = trim($_POST['lic_key']    ?? '');

    if (!$dbHost) $errors[] = 'Hôte MySQL obligatoire.';
    if (!$dbName) $errors[] = 'Nom de base obligatoire.';
    if (!$dbUser) $errors[] = 'Utilisateur MySQL obligatoire.';
    if (!$baseUrl) $errors[] = 'URL de base obligatoire.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email admin invalide.';
    if (!$adminPrenom) $errors[] = 'Prénom admin obligatoire.';
    if (mb_strlen($adminPass1) < 8) $errors[] = 'Mot de passe minimum 8 caractères.';
    if ($adminPass1 !== $adminPass2) $errors[] = 'Les mots de passe ne correspondent pas.';

    if (empty($errors)) {
        // ── Connexion + création DB ────────────────────────────────────────
        $step = 3; // Go to execution view
        ?>
<div class="step-bar"><div class="step done"></div><div class="step done"></div><div class="step current"></div><div class="step"></div></div>
<div class="section"><h2>Étape 3 — Installation en cours</h2></div>
<div class="log" id="log">
<?php
        ob_start();
        try {
            $dsn = "mysql:host=$dbHost;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            logLine('ok', "✓ Connexion MySQL ($dbHost)");
        } catch (PDOException $e) {
            logLine('fail', "✗ Impossible de se connecter : " . $e->getMessage());
            echo '</div><div class="alert alert-err">Échec connexion MySQL. Vérifiez les identifiants.</div>';
            goto end_log;
        }

        // Créer / sélectionner DB
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            logLine('ok', "✓ Base de données « $dbName » prête");
        } catch (PDOException $e) {
            logLine('fail', "✗ Impossible de créer la base : " . $e->getMessage());
            echo '</div><div class="alert alert-err">Impossible de créer la base de données.</div>';
            goto end_log;
        }

        // Schéma principal si base vide
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            logLine('info', "ℹ Base vide — application du schéma principal…");
            $schemaFile = $sqlDir . '/schema.sql';
            if (!file_exists($schemaFile)) {
                logLine('fail', "✗ schema.sql introuvable dans $sqlDir");
                goto end_log;
            }
            try {
                $pdo->exec(file_get_contents($schemaFile));
                logLine('ok', "✓ schema.sql appliqué");
            } catch (PDOException $e) {
                logLine('fail', "✗ Erreur schéma : " . $e->getMessage());
                goto end_log;
            }
        } else {
            logLine('ok', "✓ Schéma déjà présent (" . count($tables) . " tables)");
        }

        // Migrations
        $migFiles = glob($migrations . '/[0-9]*.sql') ?: [];
        natsort($migFiles);
        $applied = 0; $skipped = 0;
        foreach ($migFiles as $f) {
            $name = basename($f);
            $sql  = file_get_contents($f);
            try {
                $pdo->exec($sql);
                logLine('ok', "✓ Migration $name");
                $applied++;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'Duplicate entry') || str_contains($msg, 'already exists')) {
                    logLine('info', "  Migration $name — déjà appliquée");
                    $skipped++;
                } else {
                    logLine('warn', "! Migration $name — $msg");
                }
            }
        }
        logLine('ok', "✓ Migrations : $applied appliquées, $skipped ignorées");

        // Compte admin
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM utilisateur WHERE role_id = 3")->fetchColumn();
        if ($adminCount > 0) {
            logLine('info', "ℹ Compte admin existant — étape ignorée");
        } else {
            $hash = password_hash($adminPass1, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO utilisateur (email, password, prenom, nom, role_id, actif, must_change_password, email_verified_at) VALUES (?, ?, ?, ?, 3, 1, 0, NOW())")
                ->execute([$adminEmail, $hash, $adminPrenom ?: 'Admin', $adminNom ?: 'Traiteur']);
            logLine('ok', "✓ Compte admin créé : $adminEmail");
        }

        // Licence
        if ($licKey && $licDomain) {
            $domain  = strtolower(trim(preg_replace('#^https?://#', '', $licDomain), '/'));
            $secret  = 'tugeres_akiksystems_2025_' . $licKey;
            $licHash = hash_hmac('sha256', $domain, $secret);
            foreach ([
                ['license_key', $licKey],
                ['license_domain', $domain],
                ['license_hash', $licHash],
            ] as [$k, $v]) {
                $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
                    ->execute([$k, $v]);
            }
            logLine('ok', "✓ Licence activée pour $domain");
        } else {
            logLine('warn', "! Licence non renseignée — bandeau d'avertissement visible");
        }

        // BASE_URL en site_config
        if ($baseUrl) {
            $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES ('base_url', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
                ->execute([$baseUrl]);
            logLine('info', "ℹ BASE_URL enregistrée : $baseUrl");
        }

        // Dossiers uploads
        foreach (['public/uploads', 'public/uploads/images', 'public/uploads/menus', 'public/uploads/logos'] as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) { @mkdir($path, 0755, true); }
            logLine('ok', "✓ Dossier : $dir");
        }

        // Fichier verrou
        @mkdir(dirname($lockFile), 0755, true);
        file_put_contents($lockFile, date('c') . "\nsetup.php\n");
        logLine('ok', "✓ Verrou d'installation créé (.installed)");

        logLine('ok', "\n✓ Installation terminée avec succès !");
        $installOk = true;

        end_log:
        ob_end_flush();
        ?>
</div>
        <?php if (!empty($installOk)): ?>
<div class="big-ok">
    <span class="icon">🎉</span>
    <h2>Tugères est prêt !</h2>
    <p>Votre application est installée et opérationnelle.</p>
    <p style="margin-top:.5rem">Commencez par personnaliser votre identité dans l'espace admin.</p>
    <div class="links" style="max-width:400px;margin:1.5rem auto 0">
        <a href="/" class="link-card"><div class="lc-title">🏠 Accueil</div><div class="lc-desc">Voir le site</div></a>
        <a href="/admin" class="link-card"><div class="lc-title">⚙️ Admin</div><div class="lc-desc">Paramètres</div></a>
        <a href="/admin/parametres" class="link-card" style="grid-column:span 2"><div class="lc-title">🎨 Personnaliser l'identité</div><div class="lc-desc">Nom, couleurs, logo, SIRET…</div></a>
    </div>
</div>
        <?php endif; ?>
        </div></body></html>
        <?php
        exit;
    } // end empty($errors)

    // Errors → back to step 2 with pre-fill
    $step = 2;
}

// ── Render step bar helper ──────────────────────────────────────────────────
function stepBar(int $current): void {
    echo '<div class="step-bar">';
    for ($i = 1; $i <= 4; $i++) {
        $cls = $i < $current ? 'done' : ($i === $current ? 'current' : '');
        echo '<div class="step ' . $cls . '"></div>';
    }
    echo '</div>';
}

// ── STEP 1 — Bienvenue ──────────────────────────────────────────────────────
if ($step === 1):
    stepBar(1);
?>
<div class="section">
    <h2>Bienvenue dans l'assistant d'installation</h2>
    <p style="font-size:.88rem;color:#6b7280;margin-bottom:1.5rem">
        Ce wizard va configurer la base de données, créer le compte administrateur
        et activer votre licence Tugères en quelques minutes.
    </p>
    <ul class="check-list" style="margin-bottom:1.5rem">
        <?php
        $prereqs = [
            PHP_VERSION_ID >= 80100        => 'PHP ' . PHP_VERSION . (PHP_VERSION_ID >= 80100 ? ' ✓' : ' — PHP 8.1 requis'),
            extension_loaded('pdo_mysql')  => 'Extension pdo_mysql',
            extension_loaded('mbstring')   => 'Extension mbstring',
            file_exists($root . '/vendor/autoload.php') => 'Composer (vendor/autoload.php)',
        ];
        foreach ($prereqs as $ok => $label): ?>
            <li>
                <span class="<?= $ok ? 'ico-ok' : 'ico-fail' ?>"><?= $ok ? '✓' : '✗' ?></span>
                <?= h($label) ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php $allOk = !in_array(false, array_keys($prereqs), true); ?>
    <?php if (!$allOk): ?>
    <div class="alert alert-err">Des prérequis sont manquants. Consultez <a href="check.php">check.php</a> pour le détail.</div>
    <?php else: ?>
    <form method="GET">
        <input type="hidden" name="step" value="2">
        <button class="btn btn-full">Commencer l'installation →</button>
    </form>
    <?php endif; ?>
</div>

<?php
// ── STEP 2 — Formulaire ────────────────────────────────────────────────────
elseif ($step === 2):
    stepBar(2);
    // Pre-fill from .env
    $def = fn(string $k, string $d = '') => h($env[$k] ?? $d);
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-err">
    <?php foreach ($errors as $e): ?><?= h($e) ?><br><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="step" value="2">

    <div class="section">
        <h2>Base de données MySQL</h2>
        <div class="row2">
            <div class="form-group">
                <label>Hôte <span class="req">*</span></label>
                <input type="text" name="db_host" value="<?= $def('DB_HOST', 'localhost') ?>" placeholder="localhost" required>
            </div>
            <div class="form-group">
                <label>Nom de la base <span class="req">*</span></label>
                <input type="text" name="db_name" value="<?= $def('DB_NAME') ?>" placeholder="tugeres_prod" required>
            </div>
        </div>
        <div class="row2">
            <div class="form-group">
                <label>Utilisateur <span class="req">*</span></label>
                <input type="text" name="db_user" value="<?= $def('DB_USER') ?>" required>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="db_pass" autocomplete="current-password">
            </div>
        </div>
    </div>

    <hr class="divider">

    <div class="section">
        <h2>URL de l'application</h2>
        <div class="form-group">
            <label>URL de base <span class="req">*</span></label>
            <input type="url" name="base_url" value="<?= $def('BASE_URL', 'https://') ?>" placeholder="https://montraiteur.fr" required>
            <div class="hint">Sans slash final. Utilisée pour les emails et redirections.</div>
        </div>
    </div>

    <hr class="divider">

    <div class="section">
        <h2>Compte administrateur</h2>
        <div class="form-group">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" autocomplete="username" required>
        </div>
        <div class="row2">
            <div class="form-group">
                <label>Prénom <span class="req">*</span></label>
                <input type="text" name="admin_prenom" value="<?= h($_POST['admin_prenom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Nom</label>
                <input type="text" name="admin_nom" value="<?= h($_POST['admin_nom'] ?? '') ?>">
            </div>
        </div>
        <div class="row2">
            <div class="form-group">
                <label>Mot de passe <span class="req">*</span></label>
                <input type="password" name="admin_pass1" autocomplete="new-password" minlength="8" required>
                <div class="hint">Minimum 8 caractères.</div>
            </div>
            <div class="form-group">
                <label>Confirmer <span class="req">*</span></label>
                <input type="password" name="admin_pass2" autocomplete="new-password" required>
            </div>
        </div>
    </div>

    <hr class="divider">

    <div class="section">
        <h2>Licence Tugères <span style="font-weight:400;color:#6b7280">(optionnel)</span></h2>
        <div class="form-group">
            <label>Domaine</label>
            <input type="text" name="lic_domain" value="<?= $def('BASE_URL') ?>" placeholder="montraiteur.fr">
            <div class="hint">Domaine exact tel que configuré dans la licence.</div>
        </div>
        <div class="form-group">
            <label>Clé de licence</label>
            <input type="text" name="lic_key" placeholder="XXXX-XXXX-XXXX-XXXX">
            <div class="hint">Fournie par AkikSystems. Laissez vide si vous n'en avez pas encore.</div>
        </div>
    </div>

    <button type="submit" class="btn btn-full">Installer Tugères →</button>
</form>

<?php endif; ?>

</div>
</body>
</html>
