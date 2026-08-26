-- V1 forward-only: make order status history a true append-only audit log.
-- No trigger/SUPER privilege is required. Immutability is enforced relationally:
-- every history row is referenced by a guard row through a composite immutable key.
--
-- This migration is deliberately resumable. A failed pre-release deployment may
-- have created only derived guard columns/indexes before the migration was tracked.
-- Those derived artifacts are rebuilt; business/audit rows are never deleted.
--
-- Historical pre-V1 databases may also have equivalent foreign keys under arbitrary
-- names and non-canonical collations. Foreign keys are therefore discovered by
-- relationship, while every text-bearing immutable key is represented as BINARY(32)
-- SHA-256 data so the guard does not depend on historical charset/collation choices.

-- A guard table is fully derived from commande_historique and is safe to rebuild
-- while the application is stopped during migration startup.
DROP TABLE IF EXISTS commande_historique_guard;

-- Drop every legacy/current history -> order FK, regardless of its constraint name.
SET @history_order_fk_drops = (
    SELECT GROUP_CONCAT(
        CONCAT('DROP FOREIGN KEY `', REPLACE(CONSTRAINT_NAME, '`', '``'), '`')
        ORDER BY CONSTRAINT_NAME SEPARATOR ', '
    )
    FROM (
        SELECT DISTINCT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'commande_historique'
          AND COLUMN_NAME = 'commande_id'
          AND REFERENCED_TABLE_NAME = 'commande'
          AND REFERENCED_COLUMN_NAME = 'commande_id'
    ) history_order_fks
);
SET @drop_history_order_fks_sql = IF(
    @history_order_fk_drops IS NULL OR @history_order_fk_drops = '',
    'DO 0',
    CONCAT('ALTER TABLE `commande_historique` ', @history_order_fk_drops)
);
PREPARE drop_history_order_fks_stmt FROM @drop_history_order_fks_sql;
EXECUTE drop_history_order_fks_stmt;
DEALLOCATE PREPARE drop_history_order_fks_stmt;

-- Drop every legacy/current history -> actor FK, regardless of its constraint name.
SET @history_actor_fk_drops = (
    SELECT GROUP_CONCAT(
        CONCAT('DROP FOREIGN KEY `', REPLACE(CONSTRAINT_NAME, '`', '``'), '`')
        ORDER BY CONSTRAINT_NAME SEPARATOR ', '
    )
    FROM (
        SELECT DISTINCT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'commande_historique'
          AND COLUMN_NAME = 'modifie_par'
          AND REFERENCED_TABLE_NAME = 'utilisateur'
          AND REFERENCED_COLUMN_NAME = 'utilisateur_id'
    ) history_actor_fks
);
SET @drop_history_actor_fks_sql = IF(
    @history_actor_fk_drops IS NULL OR @history_actor_fk_drops = '',
    'DO 0',
    CONCAT('ALTER TABLE `commande_historique` ', @history_actor_fk_drops)
);
PREPARE drop_history_actor_fks_stmt FROM @drop_history_actor_fks_sql;
EXECUTE drop_history_actor_fks_stmt;
DEALLOCATE PREPARE drop_history_actor_fks_stmt;

-- Remove an untracked/partial guard index from a previous failed attempt.
SET @history_guard_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'commande_historique'
      AND INDEX_NAME = 'uk_commande_historique_immutable'
);
SET @drop_history_guard_index_sql = IF(
    @history_guard_index_exists > 0,
    'ALTER TABLE `commande_historique` DROP INDEX `uk_commande_historique_immutable`',
    'DO 0'
);
PREPARE drop_history_guard_index_stmt FROM @drop_history_guard_index_sql;
EXECUTE drop_history_guard_index_stmt;
DEALLOCATE PREPARE drop_history_guard_index_stmt;

-- Remove only derived guard columns from an untracked/partial migration attempt.
SET @history_guard_column_drops = (
    SELECT GROUP_CONCAT(
        CONCAT('DROP COLUMN `', REPLACE(COLUMN_NAME, '`', '``'), '`')
        ORDER BY ORDINAL_POSITION SEPARATOR ', '
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'commande_historique'
      AND COLUMN_NAME IN (
          'ancien_statut_guard',
          'nouveau_statut_guard',
          'commentaire_guard',
          'modifie_par_guard'
      )
);
SET @drop_history_guard_columns_sql = IF(
    @history_guard_column_drops IS NULL OR @history_guard_column_drops = '',
    'DO 0',
    CONCAT('ALTER TABLE `commande_historique` ', @history_guard_column_drops)
);
PREPARE drop_history_guard_columns_stmt FROM @drop_history_guard_columns_sql;
EXECUTE drop_history_guard_columns_stmt;
DEALLOCATE PREPARE drop_history_guard_columns_stmt;

ALTER TABLE commande_historique
    ADD CONSTRAINT fk_commande_historique_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_commande_historique_user
        FOREIGN KEY (modifie_par) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    ADD COLUMN ancien_statut_guard BINARY(32)
        GENERATED ALWAYS AS (UNHEX(SHA2(COALESCE(ancien_statut, ''), 256))) STORED,
    ADD COLUMN nouveau_statut_guard BINARY(32)
        GENERATED ALWAYS AS (UNHEX(SHA2(nouveau_statut, 256))) STORED,
    ADD COLUMN commentaire_guard BINARY(32)
        GENERATED ALWAYS AS (UNHEX(SHA2(COALESCE(commentaire, ''), 256))) STORED,
    ADD COLUMN modifie_par_guard INT
        GENERATED ALWAYS AS (COALESCE(modifie_par, 0)) STORED,
    ADD CONSTRAINT uk_commande_historique_immutable UNIQUE (
        historique_id,
        commande_id,
        ancien_statut_guard,
        nouveau_statut_guard,
        commentaire_guard,
        modifie_par_guard,
        created_at
    );

CREATE TABLE commande_historique_guard (
    historique_id INT NOT NULL PRIMARY KEY,
    commande_id INT NOT NULL,
    ancien_statut_guard BINARY(32) NOT NULL,
    nouveau_statut_guard BINARY(32) NOT NULL,
    commentaire_guard BINARY(32) NOT NULL,
    modifie_par_guard INT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_commande_historique_guard_event FOREIGN KEY (
        historique_id,
        commande_id,
        ancien_statut_guard,
        nouveau_statut_guard,
        commentaire_guard,
        modifie_par_guard,
        created_at
    ) REFERENCES commande_historique (
        historique_id,
        commande_id,
        ancien_statut_guard,
        nouveau_statut_guard,
        commentaire_guard,
        modifie_par_guard,
        created_at
    ) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lock all historical events that existed before this migration.
INSERT INTO commande_historique_guard (
    historique_id,
    commande_id,
    ancien_statut_guard,
    nouveau_statut_guard,
    commentaire_guard,
    modifie_par_guard,
    created_at
)
SELECT
    historique_id,
    commande_id,
    ancien_statut_guard,
    nouveau_statut_guard,
    commentaire_guard,
    modifie_par_guard,
    created_at
FROM commande_historique;
