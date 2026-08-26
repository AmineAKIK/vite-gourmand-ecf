-- V1 forward-only: make order status history a true append-only audit log.
-- No trigger/SUPER privilege is required. Immutability is enforced relationally:
-- every history row is referenced by a guard row through a composite immutable key.
--
-- Historical pre-V1 databases may have equivalent foreign keys under arbitrary
-- names. Discover them by relationship instead of assuming baseline names.

SET @history_order_fk = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'commande_historique'
      AND COLUMN_NAME = 'commande_id'
      AND REFERENCED_TABLE_NAME = 'commande'
      AND REFERENCED_COLUMN_NAME = 'commande_id'
    ORDER BY CONSTRAINT_NAME
    LIMIT 1
);
SET @drop_history_order_fk_sql = IF(
    @history_order_fk IS NULL,
    'DO 0',
    CONCAT(
        'ALTER TABLE `commande_historique` DROP FOREIGN KEY `',
        REPLACE(@history_order_fk, '`', '``'),
        '`'
    )
);
PREPARE drop_history_order_fk_stmt FROM @drop_history_order_fk_sql;
EXECUTE drop_history_order_fk_stmt;
DEALLOCATE PREPARE drop_history_order_fk_stmt;

SET @history_actor_fk = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'commande_historique'
      AND COLUMN_NAME = 'modifie_par'
      AND REFERENCED_TABLE_NAME = 'utilisateur'
      AND REFERENCED_COLUMN_NAME = 'utilisateur_id'
    ORDER BY CONSTRAINT_NAME
    LIMIT 1
);
SET @drop_history_actor_fk_sql = IF(
    @history_actor_fk IS NULL,
    'DO 0',
    CONCAT(
        'ALTER TABLE `commande_historique` DROP FOREIGN KEY `',
        REPLACE(@history_actor_fk, '`', '``'),
        '`'
    )
);
PREPARE drop_history_actor_fk_stmt FROM @drop_history_actor_fk_sql;
EXECUTE drop_history_actor_fk_stmt;
DEALLOCATE PREPARE drop_history_actor_fk_stmt;

ALTER TABLE commande_historique
    ADD CONSTRAINT fk_commande_historique_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_commande_historique_user
        FOREIGN KEY (modifie_par) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    ADD COLUMN ancien_statut_guard VARCHAR(50)
        GENERATED ALWAYS AS (COALESCE(ancien_statut, '')) STORED,
    ADD COLUMN commentaire_guard CHAR(64)
        GENERATED ALWAYS AS (SHA2(COALESCE(commentaire, ''), 256)) STORED,
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
    );

CREATE TABLE commande_historique_guard (
    historique_id INT NOT NULL PRIMARY KEY,
    commande_id INT NOT NULL,
    ancien_statut_guard VARCHAR(50) NOT NULL,
    nouveau_statut VARCHAR(50) NOT NULL,
    commentaire_guard CHAR(64) NOT NULL,
    modifie_par_guard INT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_commande_historique_guard_event FOREIGN KEY (
        historique_id,
        commande_id,
        ancien_statut_guard,
        nouveau_statut,
        commentaire_guard,
        modifie_par_guard,
        created_at
    ) REFERENCES commande_historique (
        historique_id,
        commande_id,
        ancien_statut_guard,
        nouveau_statut,
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
    nouveau_statut,
    commentaire_guard,
    modifie_par_guard,
    created_at
)
SELECT
    historique_id,
    commande_id,
    ancien_statut_guard,
    nouveau_statut,
    commentaire_guard,
    modifie_par_guard,
    created_at
FROM commande_historique;
