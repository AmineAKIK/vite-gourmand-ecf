<?php

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Applies forward-only schema migrations after the V1 baseline.
 *
 * Initial database creation is deliberately NOT this class's responsibility.
 * A fresh database is provisioned from sql/v1/001_v1_baseline.sql by the V1
 * provisioner. This migrator only applies later files from sql/v1/migrations.
 *
 * Guarantees:
 * - one database instance migrates at a time through a MySQL advisory lock;
 * - every applied migration is permanently bound to its SHA-256 checksum;
 * - historical files are never repaired, expanded or tolerated at runtime;
 * - any SQL failure aborts startup; there is no DDL error allow-list;
 * - HTTP schema mutation is disabled by default.
 */
final class Migrator
{
    private const LOCK_NAME = 'tugeres_schema_migrations';
    private const LOCK_TIMEOUT_SECONDS = 10;
    private const FIRST_FORWARD_VERSION = 2;

    private static bool $ran = false;

    public static function run(): void
    {
        if (!self::executionAllowed() || self::$ran) {
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
            $files = self::migrationFiles();
            self::validateFileSet($files);
            self::validateAppliedMigrations($db, $files);

            $applied = self::appliedMigrations($db);
            foreach ($files as $file) {
                $name = basename($file);
                if (array_key_exists($name, $applied)) {
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException('Migration illisible : ' . $name);
                }

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

        return strtolower(Environment::get('TUGERES_ALLOW_HTTP_MIGRATIONS', 'false')) === 'true';
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
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> */
    private static function migrationFiles(): array
    {
        $dir = dirname(__DIR__, 2) . '/sql/v1/migrations';
        $files = glob($dir . '/[0-9]*.sql') ?: [];
        natsort($files);

        return array_values($files);
    }

    /** @param list<string> $files */
    private static function validateFileSet(array $files): void
    {
        $versions = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (!preg_match('/^(\d{3})_[a-z0-9][a-z0-9_]*\.sql$/', $name, $matches)) {
                throw new RuntimeException('Nom de migration V1 invalide : ' . $name);
            }

            $version = (int) $matches[1];
            if ($version < self::FIRST_FORWARD_VERSION) {
                throw new RuntimeException(
                    'La baseline 001 est provisionnée séparément ; migration forward invalide : ' . $name
                );
            }

            if (isset($versions[$version])) {
                throw new RuntimeException(sprintf(
                    'Version de migration dupliquée %03d : %s / %s',
                    $version,
                    $versions[$version],
                    $name,
                ));
            }
            $versions[$version] = $name;
        }
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

    /** @param list<string> $files */
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

            $storedChecksum = $applied[$name];
            if ($storedChecksum === null || $storedChecksum === '') {
                throw new RuntimeException('Migration appliquée sans checksum : ' . $name);
            }

            $checksum = hash('sha256', $sql);
            if (!hash_equals($storedChecksum, $checksum)) {
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
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                throw new RuntimeException(
                    'Migration ' . $name . ' interrompue : ' . $e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO schema_migrations (migration, checksum, applied_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$name, hash('sha256', $sql)]);
        error_log('[Migrator] migration appliquée : ' . $name);
    }

    /** @return list<string> */
    private static function extractCreatedTables(string $sql): array
    {
        preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
            $sql,
            $matches,
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
}
