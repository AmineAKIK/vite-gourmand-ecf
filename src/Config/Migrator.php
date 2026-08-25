<?php

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Applies the base schema and ordered SQL migrations.
 *
 * Lifecycle guarantees:
 * - one database instance migrates at a time through a MySQL advisory lock;
 * - an applied migration is bound to its SHA-256 checksum;
 * - schema drift is reported instead of silently replaying tracked migrations;
 * - a migration is recorded only after every statement succeeded or was proven idempotent;
 * - migration failures are propagated so the application never serves on an unknown schema.
 *
 * Runtime lifecycle:
 * - CLI execution is allowed and is the production deployment path;
 * - HTTP execution is disabled by default so requests never mutate the schema;
 * - TUGERES_ALLOW_HTTP_MIGRATIONS=true is an explicit temporary compatibility escape hatch.
 */
class Migrator
{
    private const LOCK_NAME = 'tugeres_schema_migrations';
    private const LOCK_TIMEOUT_SECONDS = 10;

    private static bool $ran = false;

    public static function run(): void
    {
        if (!self::executionAllowed()) {
            return;
        }

        if (self::$ran) {
            return;
        }

        $db = Database::getConnection();
        $locked = false;

        try {
            $locked = self::acquireLock($db);
            if (!$locked) {
                throw new RuntimeException('Impossible d’obtenir le verrou de migration dans le délai imparti.');
            }

            self::ensureTrackingTable($db);
            self::applyBaseSchemaIfNeeded($db);

            $files = self::migrationFiles();
            self::validateAppliedMigrations($db, $files);

            $applied = self::appliedMigrations($db);
            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) {
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException('Migration illisible : ' . $name);
                }

                self::repairKnownPartialMigration($db, $name);
                self::applyMigration($db, $name, $sql);
            }

            self::$ran = true;
        } catch (Throwable $e) {
            error_log('[Migrator] Échec critique : ' . $e->getMessage());
            throw $e;
        } finally {
            if ($locked) {
                self::releaseLock($db);
            }
        }
    }

    private static function executionAllowed(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        return strtolower((string) ($_ENV['TUGERES_ALLOW_HTTP_MIGRATIONS'] ?? getenv('TUGERES_ALLOW_HTTP_MIGRATIONS') ?: 'false')) === 'true';
    }

    private static function acquireLock(PDO $db): bool
    {
        $stmt = $db->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([self::LOCK_NAME, self::LOCK_TIMEOUT_SECONDS]);

        return (int) $stmt->fetchColumn() === 1;
    }

    private static function releaseLock(PDO $db): void
    {
        try {
            $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([self::LOCK_NAME]);
        } catch (Throwable $e) {
            error_log('[Migrator] libération du verrou impossible : ' . $e->getMessage());
        }
    }

    private static function ensureTrackingTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(255) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        if (!self::columnExists($db, 'schema_migrations', 'checksum')) {
            $db->exec('ALTER TABLE schema_migrations ADD COLUMN checksum CHAR(64) NULL AFTER migration');
        }
    }

    private static function applyBaseSchemaIfNeeded(PDO $db): void
    {
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('utilisateur', $tables, true)) {
            return;
        }

        $schemaFile = dirname(__DIR__, 2) . '/sql/schema.sql';
        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new RuntimeException('sql/schema.sql est introuvable ou illisible.');
        }

        foreach (SqlStatementSplitter::split($sql) as $statement) {
            $db->exec($statement);
        }

        error_log('[Migrator] schema.sql appliqué');
    }

    /** @return list<string> */
    private static function migrationFiles(): array
    {
        $dir = dirname(__DIR__, 2) . '/sql/migrations';
        $files = glob($dir . '/[0-9]*.sql') ?: [];
        natsort($files);

        return array_values($files);
    }

    /** @return array<string,string|null> */
    private static function appliedMigrations(PDO $db): array
    {
        $rows = $db->query('SELECT migration, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['migration']] = $row['checksum'] !== null ? (string) $row['checksum'] : null;
        }

        return $result;
    }

    private static function validateAppliedMigrations(PDO $db, array $files): void
    {
        $applied = self::appliedMigrations($db);

        foreach ($files as $file) {
            $name = basename($file);
            if (!array_key_exists($name, $applied)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Migration appliquée mais illisible : ' . $name);
            }

            $checksum = hash('sha256', $sql);
            $storedChecksum = $applied[$name];
            if ($storedChecksum === null || $storedChecksum === '') {
                $stmt = $db->prepare('UPDATE schema_migrations SET checksum = ? WHERE migration = ? AND checksum IS NULL');
                $stmt->execute([$checksum, $name]);
            } elseif (!hash_equals($storedChecksum, $checksum)) {
                throw new RuntimeException('Migration modifiée après application : ' . $name);
            }

            foreach (self::extractCreatedTables($sql) as $table) {
                if (!self::tableExists($db, $table)) {
                    throw new RuntimeException(
                        'Dérive de schéma détectée : ' . $name . ' est appliquée mais la table ' . $table . ' manque.'
                    );
                }
            }
        }
    }

    private static function applyMigration(PDO $db, string $name, string $sql): void
    {
        $statements = SqlStatementSplitter::split($sql);
        if ($statements === []) {
            throw new RuntimeException('Migration vide : ' . $name);
        }

        foreach ($statements as $statement) {
            foreach (LegacyAlterTableCompatibility::expand($db, $name, $statement) as $runtimeStatement) {
                try {
                    $db->exec($runtimeStatement);
                } catch (PDOException $e) {
                    if (self::isProvenIdempotentError($e, $runtimeStatement)) {
                        continue;
                    }

                    throw new RuntimeException(
                        'Migration ' . $name . ' interrompue : ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO schema_migrations (migration, checksum, applied_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$name, hash('sha256', $sql)]);
        error_log('[Migrator] migration appliquée : ' . $name);
    }

    private static function isProvenIdempotentError(PDOException $e, string $statement): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($statement)) ?? trim($statement));

        if (!str_starts_with($normalized, 'ALTER TABLE ')) {
            return false;
        }

        if ($code === 1060) {
            return substr_count($normalized, ' ADD COLUMN ') === 1;
        }

        if ($code === 1091) {
            $dropCount = substr_count($normalized, ' DROP COLUMN ')
                + substr_count($normalized, ' DROP INDEX ')
                + substr_count($normalized, ' DROP KEY ');

            return $dropCount === 1;
        }

        return false;
    }

    /** @return list<string> */
    private static function extractCreatedTables(string $sql): array
    {
        preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
            $sql,
            $matches
        );

        return array_values(array_unique($matches[1] ?? []));
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function repairKnownPartialMigration(PDO $db, string $name): void
    {
        if ($name !== '031_recettes_ingredients.sql') {
            return;
        }

        if (!self::tableExists($db, 'ingredient') || self::columnExists($db, 'ingredient', 'ingredient_id')) {
            return;
        }

        $backup = self::uniqueLegacyTableName($db, 'ingredient');
        $escaped = str_replace('`', '``', $backup);
        $db->exec('RENAME TABLE `ingredient` TO `' . $escaped . '`');
        error_log('[Migrator] 031 : table ingredient incompatible sauvegardée en ' . $backup);
    }

    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function uniqueLegacyTableName(PDO $db, string $table): string
    {
        $base = $table . '_legacy_' . date('YmdHis');
        $candidate = $base;
        $suffix = 1;

        while (self::tableExists($db, $candidate)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
