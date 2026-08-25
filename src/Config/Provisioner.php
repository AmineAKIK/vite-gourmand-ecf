<?php

namespace App\Config;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Owns the one-time V1 database provisioning contract.
 *
 * - Truly empty database: apply the canonical V1 baseline and record its checksum.
 * - Complete existing database: do not replay the baseline.
 * - Partial/incompatible database: fail closed; never attempt repair.
 *
 * Post-baseline evolution remains the responsibility of Migrator.
 */
final class Provisioner
{
    private const LOCK_NAME = 'tugeres_schema_provisioning';
    private const LOCK_TIMEOUT_SECONDS = 10;
    private const BASELINE_NAME = '001_v1_baseline.sql';

    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'role',
        'utilisateur',
        'password_reset',
        'rate_limit',
        'site_config',
        'site_image',
        'horaire',
        'geocache',
        'regime',
        'theme',
        'categorie_plat',
        'menu',
        'menu_image',
        'plat',
        'menu_plat',
        'allergen',
        'plat_allergen',
        'taux_tva',
        'mode_paiement',
        'commande',
        'commande_ligne',
        'commande_historique',
        'avis',
        'notification',
        'ingredient',
        'recette_ligne',
        'mouvement_stock',
        'commande_ingredient_snapshot',
        'document_facturation',
        'document_facturation_ligne',
        'document_sequence',
        'paiement',
        'order_draft',
        'payment_attempt',
        'stripe_webhook_event',
        'payment_refund_attempt',
        'order_cancellation_effect',
        'order_admission_lock',
        'order_admission_reservation',
        'cron_rappel_log',
    ];

    public static function run(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Le provisioning de schéma est réservé au CLI/startup.');
        }

        self::runWithConnection(Database::getConnection());
    }

    public static function runWithConnection(PDO $db): void
    {
        $locked = false;

        try {
            $locked = self::acquireLock($db);
            if (!$locked) {
                throw new RuntimeException('Impossible d’obtenir le verrou de provisioning dans le délai imparti.');
            }

            $tables = self::databaseTables($db);
            if ($tables === []) {
                self::applyBaseline($db);
                return;
            }

            self::assertCompleteExistingSchema($tables);
            self::validateRecordedBaselineIfPresent($db);
        } finally {
            if ($locked) {
                self::releaseLock($db);
            }
        }
    }

    /** @return list<string> */
    public static function requiredTables(): array
    {
        return self::REQUIRED_TABLES;
    }

    private static function applyBaseline(PDO $db): void
    {
        $path = self::baselinePath();
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Baseline V1 introuvable ou illisible.');
        }

        $statements = SqlStatementSplitter::split($sql);
        if ($statements === []) {
            throw new RuntimeException('Baseline V1 vide.');
        }

        foreach ($statements as $statement) {
            $db->exec($statement);
        }

        self::assertCompleteExistingSchema(self::databaseTables($db));
        self::ensureTrackingTable($db);

        $checksum = hash('sha256', $sql);
        $stmt = $db->prepare(
            'INSERT INTO schema_migrations (migration, checksum, applied_at) VALUES (?, ?, NOW())',
        );
        $stmt->execute([self::BASELINE_NAME, $checksum]);

        error_log('[Provisioner] baseline V1 appliquée');
    }

    /** @param list<string> $tables */
    private static function assertCompleteExistingSchema(array $tables): void
    {
        $present = array_fill_keys($tables, true);
        $missing = [];
        foreach (self::REQUIRED_TABLES as $required) {
            if (!isset($present[$required])) {
                $missing[] = $required;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Base non vide mais schéma incomplet/incompatible ; tables manquantes : ' . implode(', ', $missing),
            );
        }
    }

    private static function validateRecordedBaselineIfPresent(PDO $db): void
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return;
        }

        $stmt = $db->prepare('SELECT checksum FROM schema_migrations WHERE migration = ?');
        $stmt->execute([self::BASELINE_NAME]);
        $stored = $stmt->fetchColumn();
        if ($stored === false) {
            // Temporary bridge for the current pre-release Railway database until
            // its explicit V1 reset. It must already satisfy the complete V1 table contract.
            return;
        }

        $sql = file_get_contents(self::baselinePath());
        if ($sql === false) {
            throw new RuntimeException('Baseline V1 introuvable ou illisible.');
        }

        $expected = hash('sha256', $sql);
        if (!is_string($stored) || $stored === '' || !hash_equals($stored, $expected)) {
            throw new RuntimeException('Baseline V1 modifiée après provisioning.');
        }
    }

    private static function ensureTrackingTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE schema_migrations (
                migration VARCHAR(255) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /** @return list<string> */
    private static function databaseTables(PDO $db): array
    {
        $stmt = $db->query(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME",
        );

        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $tables));
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function baselinePath(): string
    {
        return dirname(__DIR__, 2) . '/sql/v1/' . self::BASELINE_NAME;
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
            error_log('[Provisioner] libération du verrou impossible : ' . $e->getMessage());
        }
    }
}
