#!/usr/bin/env php
<?php
/**
 * Script d'installation — Traiteur SaaS
 *
 * Usage :
 *   php install/bootstrap.php
 *
 * Prérequis :
 *   - PHP 8.1+, extensions PDO + pdo_mysql
 *   - MySQL 8 accessible avec les identifiants du .env
 *   - Fichier .env rempli (copié depuis .env.example)
 */

declare(strict_types=1);

// ─── Constantes ─────────────────────────────────────────────────────────────
define('ROOT',        dirname(__DIR__));
define('SQL_DIR',     ROOT . '/sql');
define('MIGRATIONS',  SQL_DIR . '/migrations');

// ─── Couleurs terminal ───────────────────────────────────────────────────────
function ok(string $msg): void    { echo "\033[32m✓\033[0m  $msg\n"; }
function info(string $msg): void  { echo "\033[36mℹ\033[0m  $msg\n"; }
function warn(string $msg): void  { echo "\033[33m!\033[0m  $msg\n"; }
function fail(string $msg): never { echo "\033[31m✗\033[0m  $msg\n"; exit(1); }
function title(string $msg): void { echo "\n\033[1m$msg\033[0m\n" . str_repeat('─', 60) . "\n"; }
function prompt(string $q, string $default = ''): string
{
    $hint = $default !== '' ? " [$default]" : '';
    echo "  $q$hint : ";
    $v = trim((string)fgets(STDIN));
    return $v !== '' ? $v : $default;
}
function promptSecret(string $q): string
{
    echo "  $q (masqué) : ";
    if (PHP_OS_FAMILY !== 'Windows') {
        system('stty -echo');
        $v = trim((string)fgets(STDIN));
        system('stty echo');
    } else {
        $v = trim((string)fgets(STDIN));
    }
    echo "\n";
    return $v;
}

// ─── 1. Vérification PHP ────────────────────────────────────────────────────
title('Étape 1/5 — Vérification de l\'environnement');

if (PHP_VERSION_ID < 80100) {
    fail('PHP 8.1 minimum requis (version actuelle : ' . PHP_VERSION . ')');
}
ok('PHP ' . PHP_VERSION);

foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'fileinfo'] as $ext) {
    if (!extension_loaded($ext)) {
        fail("Extension PHP manquante : $ext");
    }
    ok("ext/$ext");
}

// ─── 2. Chargement du .env ──────────────────────────────────────────────────
title('Étape 2/5 — Configuration');

$envPath = ROOT . '/.env';
if (!file_exists($envPath)) {
    warn('.env introuvable — création depuis .env.example');
    if (!file_exists(ROOT . '/.env.example')) {
        fail('.env.example introuvable. Impossible de continuer.');
    }
    copy(ROOT . '/.env.example', $envPath);
    info('Fichier .env créé — veuillez le remplir puis relancer ce script.');
    exit(0);
}
ok('.env trouvé');

// Parse .env (pas de support des heredocs / multilignes intentionnel)
$env = [];
foreach (file($envPath) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (!str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $env[trim($key)] = trim($val, " \t\"'");
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

if (!$dbName || !$dbUser) {
    fail('DB_NAME et DB_USER doivent être renseignés dans .env');
}

// ─── 3. Connexion MySQL ──────────────────────────────────────────────────────
title('Étape 3/5 — Base de données');

try {
    $dsn = "mysql:host=$dbHost;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ok("Connexion MySQL ($dbHost / $dbUser)");
} catch (PDOException $e) {
    fail('Impossible de se connecter à MySQL : ' . $e->getMessage());
}

// Créer la base si nécessaire
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbName`");
ok("Base de données « $dbName » sélectionnée");

// Vérifier si schema déjà appliqué
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (empty($tables)) {
    info('Base vide — application du schéma principal…');
    $schema = file_get_contents(SQL_DIR . '/schema.sql');
    if ($schema === false) fail('schema.sql introuvable dans ' . SQL_DIR);
    try {
        $pdo->exec($schema);
        ok('schema.sql appliqué');
    } catch (PDOException $e) {
        fail('Erreur lors de l\'application du schéma : ' . $e->getMessage());
    }
} else {
    ok('Schéma déjà présent (' . count($tables) . ' tables)');
}

// Appliquer les migrations dans l'ordre
$migrationFiles = glob(MIGRATIONS . '/[0-9]*.sql');
natsort($migrationFiles);

$applied = 0;
$skipped = 0;

foreach ($migrationFiles as $file) {
    $name = basename($file);
    $sql  = file_get_contents($file);
    if ($sql === false) {
        warn("Impossible de lire $name — ignoré");
        continue;
    }
    try {
        $pdo->exec($sql);
        ok("Migration $name");
        $applied++;
    } catch (PDOException $e) {
        // INSERT IGNORE / IF NOT EXISTS → erreurs de doublons acceptables
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate entry') || str_contains($msg, 'already exists')) {
            info("Migration $name — déjà appliquée, ignorée");
            $skipped++;
        } else {
            warn("Migration $name — erreur : $msg");
        }
    }
}

ok("Migrations : $applied appliquées, $skipped ignorées (déjà présentes)");

// ─── 4. Compte administrateur ───────────────────────────────────────────────
title('Étape 4/5 — Compte administrateur');

$row = $pdo->query(
    "SELECT COUNT(*) FROM utilisateur WHERE role_id = 3"
)->fetchColumn();

if ((int)$row > 0) {
    info('Un compte administrateur existe déjà — étape ignorée.');
} else {
    info('Aucun administrateur trouvé — création du compte initial.');
    echo "\n";

    $email  = prompt('Email admin');
    while (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        warn('Email invalide.');
        $email = prompt('Email admin');
    }

    $prenom = prompt('Prénom');
    $nom    = prompt('Nom');

    $password = '';
    while (true) {
        $password = promptSecret('Mot de passe (min. 8 caractères)');
        if (mb_strlen($password) < 8) {
            warn('Mot de passe trop court (minimum 8 caractères).');
            continue;
        }
        $confirm = promptSecret('Confirmer le mot de passe');
        if ($password !== $confirm) {
            warn('Les mots de passe ne correspondent pas.');
            continue;
        }
        break;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare(
        "INSERT INTO utilisateur (email, password, prenom, nom, role_id, must_change_password)
         VALUES (?, ?, ?, ?, 3, 0)"
    );
    $stmt->execute([$email, $hash, $prenom ?: 'Admin', $nom ?: 'Traiteur']);

    ok("Compte administrateur créé : $email");
}

// ─── 5. Activation de la licence ────────────────────────────────────────────
title('Étape 5/6 — Licence Tugères');

$licenseKey = $pdo->query("SELECT valeur FROM site_config WHERE cle = 'license_key'")->fetchColumn();

if ($licenseKey && strlen($licenseKey) > 5) {
    ok('Licence déjà activée.');
} else {
    info('Activation de la licence pour ce déploiement.');
    echo "\n";

    $licDomain = prompt('Domaine du client (ex: montraiteur.fr)', parse_url($env['BASE_URL'] ?? '', PHP_URL_HOST) ?: 'localhost');
    $licKey    = prompt('Clé de licence (fournie par AkikSystems)');

    if (!$licKey) {
        warn('Aucune clé fournie — licence non activée. Le bandeau d\'avertissement s\'affichera.');
    } else {
        $licDomain = strtolower(trim(preg_replace('#^https?://#', '', $licDomain), '/'));
        $secret    = 'tugeres_akiksystems_2025_' . $licKey;
        $hash      = hash_hmac('sha256', $licDomain, $secret);

        $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES ('license_key', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")->execute([$licKey]);
        $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES ('license_domain', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")->execute([$licDomain]);
        $pdo->prepare("INSERT INTO site_config (cle, valeur) VALUES ('license_hash', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")->execute([$hash]);

        ok("Licence activée pour $licDomain");
    }
}

// ─── 6. Vérifications finales ────────────────────────────────────────────────
title('Étape 6/6 — Vérifications finales');

// site_config minimal
$keys = $pdo->query(
    "SELECT COUNT(*) FROM site_config WHERE cle IN ('site_nom','couleur_principale')"
)->fetchColumn();

if ((int)$keys >= 2) {
    ok('site_config : clés de base présentes');
} else {
    warn('site_config incomplet — relancer les migrations ou vérifier 017_white_label.sql');
}

// Dossiers uploads
foreach (['public/uploads', 'public/uploads/images', 'public/uploads/menus'] as $dir) {
    $path = ROOT . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        ok("Dossier créé : $dir");
    } else {
        ok("Dossier présent : $dir");
    }
}

// Résumé
$baseUrl = $env['BASE_URL'] ?? 'http://localhost:8080';
echo "\n";
echo "\033[1;32m╔══════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;32m║  Installation terminée avec succès           ║\033[0m\n";
echo "\033[1;32m╚══════════════════════════════════════════════╝\033[0m\n\n";
echo "  Accès au site    : \033[36m$baseUrl\033[0m\n";
echo "  Espace admin     : \033[36m$baseUrl/admin\033[0m\n";
echo "  Paramètres       : \033[36m$baseUrl/admin/parametres\033[0m\n\n";
echo "  Prochaines étapes :\n";
echo "    1. Personnaliser le nom, les couleurs et le logo dans Admin → Paramètres\n";
echo "    2. Renseigner les informations entreprise (SIRET, IBAN) pour la facturation\n";
echo "    3. Configurer Stripe et Brevo dans .env pour les paiements et emails\n";
echo "    4. Activer Cloudinary dans .env pour le stockage des images en production\n\n";
