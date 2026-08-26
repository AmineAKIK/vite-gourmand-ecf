#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Config\Database;

$db = Database::getConnection();

try {
    // Reproduce the pre-V1 production divergence: equivalent foreign keys under
    // historical names and text columns using a different collation.
    $db->exec(
        'ALTER TABLE commande_historique
            DROP FOREIGN KEY fk_commande_historique_commande,
            DROP FOREIGN KEY fk_commande_historique_user',
    );
    $db->exec(
        'ALTER TABLE commande_historique
            ADD CONSTRAINT legacy_history_order_fk
                FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
            ADD CONSTRAINT legacy_history_actor_fk
                FOREIGN KEY (modifie_par) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL',
    );
    $db->exec(
        'ALTER TABLE commande_historique
            MODIFY ancien_statut VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
            MODIFY nouveau_statut VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
            MODIFY commentaire TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL',
    );

    // Reproduce the exact untracked DDL artifacts left by the failed Railway
    // deployment after canonical FKs/guard columns/index had already been added,
    // but before commande_historique_guard could be created and migration 004 tracked.
    $db->exec(
        'ALTER TABLE commande_historique
            DROP FOREIGN KEY legacy_history_order_fk,
            DROP FOREIGN KEY legacy_history_actor_fk',
    );
    $db->exec(
        'ALTER TABLE commande_historique
            ADD CONSTRAINT fk_commande_historique_commande
                FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
            ADD CONSTRAINT fk_commande_historique_user
                FOREIGN KEY (modifie_par) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
            ADD COLUMN ancien_statut_guard VARCHAR(50)
                GENERATED ALWAYS AS (COALESCE(ancien_statut, \"\")) STORED,
            ADD COLUMN commentaire_guard CHAR(64)
                GENERATED ALWAYS AS (SHA2(COALESCE(commentaire, \"\"), 256)) STORED,
            ADD COLUMN modifie_par_guard INT
                GENERATED ALWAYS AS (COALESCE(modifie_par, 0)) STORED,
            ADD CONSTRAINT uk_commande_historique_immutable UNIQUE (
                historique_id,
                commande_id,
                ancien_statut_guard,
                nouveau_statut,
                commentaire_guard,
                modifie_par_guard,
                created_at
            )',
    );

    $tracked = $db->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = ?');
    $tracked->execute(['004_immutable_order_status_history.sql']);
    if ((int) $tracked->fetchColumn() !== 0) {
        throw new RuntimeException('Fixture invalide : la migration 004 ne doit pas être trackée.');
    }

    fwrite(STDOUT, "Railway partial order-history migration fixture prepared.\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'Unable to prepare Railway partial history fixture: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
