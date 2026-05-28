<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tugères — Vérification des prérequis</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f4f5;color:#1a1a2e;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.10);padding:2.5rem 2rem;width:100%;max-width:640px}
h1{font-size:1.5rem;font-weight:700;margin-bottom:.25rem;color:#8B1A2B}
.subtitle{color:#6b7280;font-size:.9rem;margin-bottom:2rem}
.section{margin-bottom:1.75rem}
.section h2{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin-bottom:.75rem}
.item{display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid #f0f0f0}
.item:last-child{border-bottom:none}
.icon{width:22px;text-align:center;font-size:1rem;flex-shrink:0}
.ok{color:#16a34a}.warn{color:#d97706}.fail{color:#dc2626}
.label{font-size:.88rem;flex:1}
.badge{font-size:.75rem;padding:.2rem .5rem;border-radius:6px;font-weight:600}
.badge-ok{background:#dcfce7;color:#15803d}
.badge-warn{background:#fef3c7;color:#b45309}
.badge-fail{background:#fee2e2;color:#b91c1c}
.note{font-size:.75rem;color:#9ca3af;margin-left:2rem;margin-top:.1rem}
.summary{margin-top:2rem;padding:1.25rem;border-radius:8px;font-size:.9rem;text-align:center}
.summary.all-ok{background:#dcfce7;color:#166534}
.summary.has-warn{background:#fef3c7;color:#92400e}
.summary.has-fail{background:#fee2e2;color:#991b1b}
.btn{display:inline-block;margin-top:1.5rem;padding:.75rem 2rem;background:#8B1A2B;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem}
.btn:hover{background:#6b1420}
</style>
</head>
<body>
<div class="card">
<h1>Tugères</h1>
<p class="subtitle">Vérification des prérequis système avant installation</p>

<?php
$root     = dirname(__DIR__);
$checks   = [];
$hasWarn  = false;
$hasFail  = false;

function chk(string $label, bool|string $status, string $value = '', string $note = ''): array {
    return compact('label', 'status', 'value', 'note');
}

// ── PHP version ──────────────────────────────────────────────────────────────
$phpOk = PHP_VERSION_ID >= 80100;
if (!$phpOk) $hasFail = true;
$checks['php'][] = chk('Version PHP', $phpOk, PHP_VERSION, $phpOk ? '' : 'PHP 8.1 minimum requis');

// ── Extensions PHP ───────────────────────────────────────────────────────────
$required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'fileinfo', 'openssl'];
$optional = ['curl', 'zip', 'intl'];
foreach ($required as $ext) {
    $ok = extension_loaded($ext);
    if (!$ok) $hasFail = true;
    $checks['php'][] = chk("Extension $ext", $ok, $ok ? 'chargée' : 'MANQUANTE');
}
foreach ($optional as $ext) {
    $ok = extension_loaded($ext);
    if (!$ok) $hasWarn = true;
    $checks['php'][] = chk("Extension $ext (optionnelle)", $ok ? true : 'warn', $ok ? 'chargée' : 'non chargée', $ok ? '' : 'Recommandée mais non bloquante');
}

// ── Fichier .env ─────────────────────────────────────────────────────────────
$envExists = file_exists($root . '/.env');
if (!$envExists) $hasWarn = true;
$checks['config'][] = chk('Fichier .env', $envExists ? true : 'warn',
    $envExists ? 'présent' : 'absent',
    $envExists ? '' : 'Copier .env.example → .env et remplir les variables');

// Parse .env si présent
$env = [];
if ($envExists) {
    foreach (file($root . '/.env') as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

$envVars = [
    'DB_HOST'    => ['required' => true,  'label' => 'DB_HOST'],
    'DB_NAME'    => ['required' => true,  'label' => 'DB_NAME'],
    'DB_USER'    => ['required' => true,  'label' => 'DB_USER'],
    'DB_PASS'    => ['required' => false, 'label' => 'DB_PASS'],
    'BASE_URL'   => ['required' => true,  'label' => 'BASE_URL'],
    'APP_ENV'    => ['required' => false, 'label' => 'APP_ENV'],
    'BREVO_API_KEY'         => ['required' => false, 'label' => 'BREVO_API_KEY (emails)'],
    'STRIPE_SECRET_KEY'     => ['required' => false, 'label' => 'STRIPE_SECRET_KEY (paiements)'],
    'CLOUDINARY_CLOUD_NAME' => ['required' => false, 'label' => 'CLOUDINARY_CLOUD_NAME (images)'],
];

foreach ($envVars as $key => $meta) {
    $set = !empty($env[$key]) || !empty($_ENV[$key]) || !empty(getenv($key));
    $val = $set ? '(définie)' : 'non définie';
    if ($meta['required'] && !$set) {
        $hasFail = true;
        $checks['config'][] = chk($meta['label'], false, $val, 'Variable obligatoire');
    } elseif (!$meta['required'] && !$set) {
        $hasWarn = true;
        $checks['config'][] = chk($meta['label'], 'warn', $val, 'Fonctionnalité désactivée si absente');
    } else {
        $checks['config'][] = chk($meta['label'], true, $val);
    }
}

// ── Connexion MySQL ───────────────────────────────────────────────────────────
$dbHost = $env['DB_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbName = $env['DB_NAME'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$dbUser = $env['DB_USER'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$dbPass = $env['DB_PASS'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

$pdo     = null;
$dbError = '';
if ($dbName && $dbUser) {
    try {
        $pdo = new PDO(
            "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
            $dbUser, $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $dbVer = $pdo->query('SELECT VERSION()')->fetchColumn();
        $checks['db'][] = chk("Connexion MySQL ($dbHost)", true, $dbVer);

        // Version MySQL >= 8
        $vMaj = (int)explode('.', $dbVer)[0];
        if ($vMaj < 8) { $hasWarn = true; }
        $checks['db'][] = chk('MySQL 8.0+', $vMaj >= 8 ? true : 'warn', $dbVer, $vMaj < 8 ? 'MySQL 8 recommandé' : '');

        // Tables existantes ?
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $installed = in_array('utilisateur', $tables, true);
        $checks['db'][] = chk('Schéma installé', $installed ? true : 'warn',
            $installed ? count($tables) . ' tables' : 'base vide — setup.php créera le schéma',
            $installed ? '' : 'Normal sur une nouvelle installation');
    } catch (PDOException $e) {
        $hasFail = true;
        $dbError = $e->getMessage();
        $checks['db'][] = chk("Connexion MySQL ($dbHost)", false, 'ÉCHEC', $dbError);
    }
} else {
    $hasWarn = true;
    $checks['db'][] = chk('Connexion MySQL', 'warn', 'impossible', 'DB_HOST / DB_NAME / DB_USER manquants dans .env');
}

// ── Dossiers écriture ─────────────────────────────────────────────────────────
$dirs = [
    'public/uploads'        => 'Uploads images',
    'public/uploads/menus'  => 'Uploads menus',
    'public/uploads/logos'  => 'Uploads logos',
];
foreach ($dirs as $rel => $label) {
    $path = $root . '/' . $rel;
    if (!is_dir($path)) @mkdir($path, 0755, true);
    $writable = is_dir($path) && is_writable($path);
    if (!$writable) $hasWarn = true;
    $checks['fs'][] = chk($label . ' (' . $rel . ')', $writable ? true : 'warn',
        $writable ? 'accessible en écriture' : 'non accessible',
        $writable ? '' : 'Vérifier les permissions (chmod 755)');
}

// ── Composer autoload ─────────────────────────────────────────────────────────
$autoload = file_exists($root . '/vendor/autoload.php');
if (!$autoload) $hasFail = true;
$checks['deps'][] = chk('vendor/autoload.php (Composer)', $autoload,
    $autoload ? 'présent' : 'ABSENT',
    $autoload ? '' : 'Lancer : composer install --no-dev --optimize-autoloader');

// ── setup.php déjà exécuté ? ──────────────────────────────────────────────────
$installed = file_exists($root . '/public/uploads/.installed');
if ($installed) $hasWarn = true;
$checks['install'][] = chk('Fichier .installed', $installed ? 'warn' : true,
    $installed ? 'présent — installation déjà effectuée' : 'absent — prêt pour setup.php',
    $installed ? 'setup.php est désactivé. Supprimer .installed seulement pour réinstaller.' : '');

// ─────────────────────────────────────────────────────────────────────────────
// Rendu
// ─────────────────────────────────────────────────────────────────────────────
$sections = [
    'php'     => 'PHP & Extensions',
    'config'  => 'Configuration (.env)',
    'db'      => 'Base de données MySQL',
    'fs'      => 'Système de fichiers',
    'deps'    => 'Dépendances PHP (Composer)',
    'install' => 'État d\'installation',
];

foreach ($sections as $key => $title):
    if (empty($checks[$key])) continue;
?>
<div class="section">
    <h2><?= htmlspecialchars($title) ?></h2>
    <?php foreach ($checks[$key] as $item):
        if ($item['status'] === true)       { $cls = 'ok';   $bcls = 'badge-ok';   $icon = '✓'; }
        elseif ($item['status'] === 'warn') { $cls = 'warn'; $bcls = 'badge-warn'; $icon = '!'; }
        else                                { $cls = 'fail'; $bcls = 'badge-fail'; $icon = '✗'; }
    ?>
    <div class="item">
        <span class="icon <?= $cls ?>"><?= $icon ?></span>
        <span class="label"><?= htmlspecialchars($item['label']) ?></span>
        <span class="badge <?= $bcls ?>"><?= htmlspecialchars($item['value']) ?></span>
    </div>
    <?php if ($item['note']): ?>
    <div class="note"><?= htmlspecialchars($item['note']) ?></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="summary <?= $hasFail ? 'has-fail' : ($hasWarn ? 'has-warn' : 'all-ok') ?>">
<?php if ($hasFail): ?>
    <strong>Prérequis manquants.</strong> Corriger les points rouges avant de lancer l'installation.
<?php elseif ($hasWarn): ?>
    <strong>Prêt avec avertissements.</strong> Les points jaunes sont optionnels mais recommandés.
<?php else: ?>
    <strong>Tout est en ordre.</strong> Vous pouvez lancer l'installation.
<?php endif; ?>
</div>

<?php if (!$hasFail && !$installed): ?>
<div style="text-align:center">
    <a href="setup.php" class="btn">Lancer l'installation →</a>
</div>
<?php elseif ($installed): ?>
<p style="margin-top:1.5rem;color:#6b7280;font-size:.85rem;text-align:center">
    Installation déjà effectuée. Accédez à votre site directement.
</p>
<?php endif; ?>

</div>
</body>
</html>
