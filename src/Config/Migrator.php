<?php

namespace App\Config;

/**
 * Applique automatiquement les migrations SQL manquantes au démarrage.
 *
 * - Crée une table schema_migrations (si absente) pour tracker les migrations déjà jouées.
 * - Compare les fichiers sql/migrations/*.sql avec la table.
 * - Applique uniquement les nouvelles migrations, dans l'ordre numérique.
 * - Fail-silent : une migration qui échoue est loguée mais ne plante pas l'app.
 */
class Migrator
{
    private static bool $ran = false;

    public static function run(): void
    {
        if (self::$ran) return;
        self::$ran = true;

        try {
            $db = Database::getConnection();

            // Table de tracking
            $db->exec("
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    migration VARCHAR(255) NOT NULL PRIMARY KEY,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Migrations déjà appliquées
            $applied = $db->query("SELECT migration FROM schema_migrations")
                          ->fetchAll(\PDO::FETCH_COLUMN);
            $applied = array_flip($applied);

            // Fichiers disponibles
            $dir   = dirname(__DIR__, 2) . '/sql/migrations';
            $files = glob($dir . '/[0-9]*.sql') ?: [];
            natsort($files);

            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) continue;

                $sql = file_get_contents($file);
                if ($sql === false) continue;

                try {
                    $db->exec($sql);
                    $db->prepare("INSERT IGNORE INTO schema_migrations (migration) VALUES (?)")
                       ->execute([$name]);
                } catch (\Throwable $e) {
                    // Migration déjà partiellement appliquée (ex: colonne existe déjà) → marquer quand même
                    $msg = $e->getMessage();
                    if (
                        str_contains($msg, 'Duplicate entry') ||
                        str_contains($msg, 'already exists') ||
                        str_contains($msg, 'Duplicate column')
                    ) {
                        $db->prepare("INSERT IGNORE INTO schema_migrations (migration) VALUES (?)")
                           ->execute([$name]);
                    } else {
                        error_log('[Migrator] ' . $name . ' : ' . $msg);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[Migrator] Échec : ' . $e->getMessage());
        }
    }
}
