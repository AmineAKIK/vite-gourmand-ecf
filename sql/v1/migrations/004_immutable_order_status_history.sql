-- V1 forward-only: make order status history a true append-only audit log.
-- No trigger/SUPER privilege is required. Immutability is enforced relationally:
-- every history row is referenced by a guard row through a composite immutable key.

ALTER TABLE commande_historique
    DROP FOREIGN KEY fk_commande_historique_commande,
    DROP FOREIGN KEY fk_commande_historique_user;

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
