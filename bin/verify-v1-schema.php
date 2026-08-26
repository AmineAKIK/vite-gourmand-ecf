#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Config\Database;
use App\Config\Provisioner;

$db = Database::getConnection();

/** @return list<string> */
function databaseTables(PDO $db): array
{
    $rows = $db->query(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME",
    )->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_map('strval', $rows));
}

/** @return list<string> */
function tableColumns(PDO $db, string $table): array
{
    $stmt = $db->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION',
    );
    $stmt->execute([$table]);

    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function scalarInt(PDO $db, string $sql): int
{
    return (int) $db->query($sql)->fetchColumn();
}

/** @param list<string> $required @param list<string> $forbidden */
function assertColumns(PDO $db, string $table, array $required, array $forbidden): void
{
    $columns = array_fill_keys(tableColumns($db, $table), true);
    foreach ($required as $column) {
        if (!isset($columns[$column])) {
            throw new RuntimeException(sprintf('Colonne V1 manquante : %s.%s', $table, $column));
        }
    }
    foreach ($forbidden as $column) {
        if (isset($columns[$column])) {
            throw new RuntimeException(sprintf('Colonne monétaire pré-V1 encore présente : %s.%s', $table, $column));
        }
    }
}

function assertCanonicalV1(PDO $db): void
{
    $tables = array_fill_keys(databaseTables($db), true);
    foreach (Provisioner::requiredTables() as $required) {
        if (!isset($tables[$required])) {
            throw new RuntimeException('Table V1 manquante : ' . $required);
        }
    }

    if (isset($tables['geocache']) || isset($tables['plat_allergene'])) {
        throw new RuntimeException('Le schéma V1 contient une table pré-release obsolète.');
    }

    if (!isset($tables['schema_migrations'])) {
        throw new RuntimeException('Table schema_migrations manquante.');
    }

    $baselinePath = dirname(__DIR__) . '/sql/v1/001_v1_baseline.sql';
    $baseline = file_get_contents($baselinePath);
    if ($baseline === false) {
        throw new RuntimeException('Baseline V1 illisible.');
    }

    $stmt = $db->prepare('SELECT checksum FROM schema_migrations WHERE migration = ?');
    $stmt->execute(['001_v1_baseline.sql']);
    $storedChecksum = $stmt->fetchColumn();
    if (!is_string($storedChecksum) || !hash_equals(hash('sha256', $baseline), $storedChecksum)) {
        throw new RuntimeException('Checksum baseline V1 invalide.');
    }

    $forward = glob(dirname(__DIR__) . '/sql/v1/migrations/[0-9]*.sql') ?: [];
    $expectedMigrationCount = 1 + count($forward);
    $actualMigrationCount = scalarInt($db, 'SELECT COUNT(*) FROM schema_migrations');
    if ($actualMigrationCount !== $expectedMigrationCount) {
        throw new RuntimeException(sprintf(
            'Tracking migration incomplet : attendu %d, obtenu %d.',
            $expectedMigrationCount,
            $actualMigrationCount,
        ));
    }

    assertColumns($db, 'commande', [
        'prix_total_cents',
        'currency',
    ], [
        'prix_total',
    ]);
    assertColumns($db, 'commande_ligne', [
        'prix_menu_cents',
        'prix_livraison_cents',
        'prix_total_ligne_cents',
        'prix_par_personne_snapshot_cents',
        'taux_tva_menu_basis_points',
        'taux_tva_livraison_basis_points',
        'taux_reduction_basis_points',
        'remise_appliquee_cents',
        'taux_tva_menu_id',
        'taux_tva_livraison_id',
    ], [
        'prix_menu',
        'prix_livraison',
        'prix_total_ligne',
        'prix_par_personne_snapshot',
        'taux_tva_snapshot',
        'taux_reduction_snapshot',
        'remise_appliquee',
        'taux_tva_id',
    ]);
    assertColumns($db, 'paiement', ['montant_cents'], ['montant']);

    foreach (['v_paiements_commande', 'v_ca_stats', 'v_ca_commandes', 'v_ca_mensuel', 'v_ca_par_menu'] as $view) {
        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        );
        $stmt->execute([$view]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Vue V1 manquante : ' . $view);
        }
    }

    $roles = $db->query('SELECT role_id, libelle FROM role ORDER BY role_id')->fetchAll(PDO::FETCH_ASSOC);
    $expectedRoles = [
        ['role_id' => 1, 'libelle' => 'utilisateur'],
        ['role_id' => 2, 'libelle' => 'employe'],
        ['role_id' => 3, 'libelle' => 'administrateur'],
    ];
    foreach ($roles as &$role) {
        $role['role_id'] = (int) $role['role_id'];
    }
    unset($role);
    if ($roles !== $expectedRoles) {
        throw new RuntimeException('Référentiel des rôles V1 invalide.');
    }

    if (scalarInt($db, 'SELECT COUNT(*) FROM allergen') !== 14) {
        throw new RuntimeException('Le référentiel INCO doit contenir exactement 14 allergènes.');
    }

    foreach (['site_config', 'menu', 'theme', 'regime', 'mode_paiement', 'taux_tva'] as $table) {
        if (scalarInt($db, 'SELECT COUNT(*) FROM `' . $table . '`') !== 0) {
            throw new RuntimeException('Le baseline V1 contient des données configurables interdites : ' . $table);
        }
    }
}

/** @return array<string,mixed> */
function schemaFingerprint(PDO $db): array
{
    $queries = [
        'tables' => "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                     ORDER BY TABLE_NAME",
        'columns' => "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, IS_NULLABLE,
                             DATA_TYPE, COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA
                      FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                      ORDER BY TABLE_NAME, ORDINAL_POSITION",
        'indexes' => "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
                      FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE()
                      ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
        'constraints' => "SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE
                          FROM information_schema.TABLE_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA = DATABASE()
                          ORDER BY TABLE_NAME, CONSTRAINT_NAME",
        'migrations' => 'SELECT migration, checksum FROM schema_migrations ORDER BY migration',
    ];

    $fingerprint = [];
    foreach ($queries as $key => $sql) {
        $fingerprint[$key] = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    return $fingerprint;
}

try {
    assertCanonicalV1($db);

    if (($argv[1] ?? '') === '--fingerprint') {
        echo json_encode(schemaFingerprint($db), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        fwrite(STDOUT, "V1 schema verified.\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'V1 schema verification failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
