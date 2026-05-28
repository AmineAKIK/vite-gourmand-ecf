<?php

namespace App\Config;

/**
 * Applique automatiquement le schéma et les migrations SQL au démarrage.
 *
 * Ordre d'exécution :
 *   1. Crée schema_migrations si absente
 *   2. Si la base est vide (pas de table `utilisateur`), applique sql/schema.sql
 *   3. Applique les migrations sql/migrations/*.sql non encore jouées, dans l'ordre
 *
 * Compatibilité MySQL :
 *   - ADD COLUMN IF NOT EXISTS n'est supporté qu'à partir de MySQL 8.0.29.
 *     Les erreurs 1060 (Duplicate column) et 1091 (Can't DROP non-existent) sont
 *     traitées comme des succès (colonne déjà présente = migration déjà partiellement jouée).
 *   - Les erreurs sur les seeds (tables manquantes avant migration 023) sont loguées
 *     mais ne bloquent pas les migrations suivantes.
 *
 * Fail-silent : toute erreur non fatale est loguée, jamais propagée.
 */
class Migrator
{
    private static bool $ran = false;

    // Codes MySQL traités comme "déjà fait, continuer"
    private const IDEMPOTENT_CODES = [
        1060, // Duplicate column name
        1061, // Duplicate key name
        1062, // Duplicate entry
        1091, // Can't DROP; check that column/key exists
        1050, // Table already exists
    ];

    public static function run(): void
    {
        if (self::$ran) return;
        self::$ran = true;

        try {
            $db = Database::getConnection();

            // ── 1. Table de tracking ─────────────────────────────────────────
            $db->exec("
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    migration  VARCHAR(255) NOT NULL PRIMARY KEY,
                    applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // ── 2. Schéma de base si DB vide ─────────────────────────────────
            $tables   = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            $hasUsers = in_array('utilisateur', $tables, true);

            // Rien à pré-remplir : toutes les migrations non encore dans schema_migrations
            // seront jouées normalement. Les erreurs idempotentes (colonne déjà présente,
            // table déjà existante) sont silencieuses — sûr sur une DB existante.

            if (!$hasUsers) {
                $schemaFile = dirname(__DIR__, 2) . '/sql/schema.sql';
                if (file_exists($schemaFile)) {
                    try {
                        $db->exec(file_get_contents($schemaFile));
                        error_log('[Migrator] schema.sql appliqué');
                    } catch (\Throwable $e) {
                        error_log('[Migrator] schema.sql : ' . $e->getMessage());
                    }
                }
            }

            // ── 3. Migrations ────────────────────────────────────────────────
            $applied = array_flip(
                $db->query("SELECT migration FROM schema_migrations")->fetchAll(\PDO::FETCH_COLUMN)
            );

            $dir   = dirname(__DIR__, 2) . '/sql/migrations';
            $files = glob($dir . '/[0-9]*.sql') ?: [];
            natsort($files);

            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) continue;

                $sql = file_get_contents($file);
                if ($sql === false) continue;

                self::applyMigration($db, $name, $sql);
            }

        } catch (\Throwable $e) {
            error_log('[Migrator] Échec critique : ' . $e->getMessage());
        }
    }

    private static function applyMigration(\PDO $db, string $name, string $sql): void
    {
        // Découpe le fichier en statements individuels pour gérer les erreurs par statement
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*\n/', $sql)),
            fn($s) => $s !== ''
        );

        $hasError = false;
        foreach ($statements as $stmt) {
            if (trim($stmt) === '') continue;
            try {
                $db->exec($stmt);
            } catch (\PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                if (in_array($code, self::IDEMPOTENT_CODES, true)) {
                    // Déjà appliqué partiellement — ignorer silencieusement
                    continue;
                }
                // Erreur réelle — loguer mais continuer les autres statements
                error_log('[Migrator] ' . $name . ' : ' . $e->getMessage());
                $hasError = true;
            }
        }

        // Marquer comme appliquée même en cas d'erreur partielle
        // (les colonnes/tables manquantes seront gérées par ensureSchema() dans les models)
        try {
            $db->prepare("INSERT IGNORE INTO schema_migrations (migration) VALUES (?)")
               ->execute([$name]);
        } catch (\Throwable) {}
    }
}
