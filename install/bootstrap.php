#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Tugères V1 — installation CLI.
 *
 * This script orchestrates onboarding only. Database schema creation/evolution
 * is delegated exclusively to Provisioner + Migrator; no SQL migration engine
 * is duplicated here.
 */

$root = dirname(__DIR__);
$envPath = $root . '/.env';

function ok(string $message): void
{
    fwrite(STDOUT, "✓ {$message}\n");
}

function info(string $message): void
{
    fwrite(STDOUT, "ℹ {$message}\n");
}

function fail(string $message): never
{
    fwrite(STDERR, "✗ {$message}\n");
    exit(1);
}

function prompt(string $label): string
{
    fwrite(STDOUT, $label . ' : ');
    return trim((string) fgets(STDIN));
}

function promptSecret(string $label): string
{
    fwrite(STDOUT, $label . ' : ');
    $masked = PHP_OS_FAMILY !== 'Windows';
    if ($masked) {
        shell_exec('stty -echo');
    }

    try {
        return trim((string) fgets(STDIN));
    } finally {
        if ($masked) {
            shell_exec('stty echo');
        }
        fwrite(STDOUT, PHP_EOL);
    }
}

if (!is_file($envPath)) {
    $example = $root . '/.env.example';
    if (!is_file($example) || !copy($example, $envPath)) {
        fail('Impossible de créer .env depuis .env.example.');
    }
    info('.env créé depuis .env.example. Renseignez-le puis relancez ce script.');
    exit(0);
}

require_once $root . '/src/Config/config.php';

use App\Config\Database;
use App\Config\Migrator;
use App\Config\Provisioner;
use App\Security\Password;

try {
    Provisioner::run();
    Migrator::run();
    ok('Schéma base de données prêt.');
} catch (Throwable $error) {
    fail('Préparation de la base impossible : ' . $error->getMessage());
}

$db = Database::getConnection();
$adminCount = (int) $db->query('SELECT COUNT(*) FROM utilisateur WHERE role_id = 3')->fetchColumn();

if ($adminCount === 0) {
    info('Création du premier compte administrateur.');

    do {
        $email = prompt('Email admin');
    } while (!filter_var($email, FILTER_VALIDATE_EMAIL));

    $prenom = prompt('Prénom');
    $nom = prompt('Nom');

    do {
        $password = promptSecret('Mot de passe admin');
        if (!Password::validate($password)) {
            info(Password::policyMessage());
            continue;
        }

        $confirmation = promptSecret('Confirmer le mot de passe');
        if (!hash_equals($password, $confirmation)) {
            info('Les mots de passe ne correspondent pas.');
            $password = '';
        }
    } while ($password === '');

    $stmt = $db->prepare(
        'INSERT INTO utilisateur
            (email, password, prenom, nom, role_id, actif, must_change_password, email_verified_at)
         VALUES (?, ?, ?, ?, 3, 1, 0, NOW())'
    );
    $stmt->execute([
        strtolower($email),
        Password::hash($password),
        $prenom !== '' ? $prenom : 'Admin',
        $nom !== '' ? $nom : 'Administrateur',
    ]);

    ok('Compte administrateur créé.');
} else {
    ok('Compte administrateur déjà présent.');
}

info('La configuration métier/white-label se poursuit dans le back-office.');
info('La licence, les plans et la monétisation éditeur ne font pas partie du provisioning produit V1.');
