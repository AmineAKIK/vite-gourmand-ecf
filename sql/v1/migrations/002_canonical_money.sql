-- V1 forward-only: canonical transactional money uses integer minor units.
-- Existing DECIMAL values are converted exactly once, then the old columns are removed.
-- Historical pre-V1 installations may contain the same columns without the named
-- constraints introduced by the V1 baseline. Constraint removal is therefore
-- conditional on information_schema, while the target schema remains strict.

DROP VIEW IF EXISTS v_ca_par_menu;
DROP VIEW IF EXISTS v_ca_mensuel;
DROP VIEW IF EXISTS v_ca_commandes;
DROP VIEW IF EXISTS v_ca_stats;
DROP VIEW IF EXISTS v_paiements_commande;

SET @ddl = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE commande DROP CHECK chk_commande_prix_total',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'commande'
      AND CONSTRAINT_NAME = 'chk_commande_prix_total'
      AND CONSTRAINT_TYPE = 'CHECK'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

ALTER TABLE commande
    ADD COLUMN prix_total_cents BIGINT UNSIGNED NULL AFTER code_postal_livraison,
    ADD COLUMN currency CHAR(3) NULL AFTER prix_total_cents;
UPDATE commande
SET prix_total_cents = CAST(ROUND(prix_total * 100) AS UNSIGNED),
    currency = 'EUR';
ALTER TABLE commande
    DROP COLUMN prix_total,
    MODIFY prix_total_cents BIGINT UNSIGNED NOT NULL,
    MODIFY currency CHAR(3) NOT NULL,
    ADD CONSTRAINT chk_commande_prix_total_cents CHECK (prix_total_cents >= 0),
    ADD CONSTRAINT chk_commande_currency CHECK (currency REGEXP '^[A-Z]{3}$');

SET @ddl = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE commande_ligne DROP FOREIGN KEY fk_commande_ligne_taux_tva',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'commande_ligne'
      AND CONSTRAINT_NAME = 'fk_commande_ligne_taux_tva'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @ddl = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE commande_ligne DROP CHECK chk_commande_ligne_montants',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'commande_ligne'
      AND CONSTRAINT_NAME = 'chk_commande_ligne_montants'
      AND CONSTRAINT_TYPE = 'CHECK'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

ALTER TABLE commande_ligne
    RENAME COLUMN taux_tva_id TO taux_tva_menu_id,
    ADD COLUMN prix_menu_cents BIGINT UNSIGNED NULL AFTER nombre_personne,
    ADD COLUMN prix_livraison_cents BIGINT UNSIGNED NULL AFTER prix_menu_cents,
    ADD COLUMN prix_total_ligne_cents BIGINT UNSIGNED NULL AFTER prix_livraison_cents,
    ADD COLUMN prix_par_personne_snapshot_cents BIGINT UNSIGNED NULL AFTER prix_total_ligne_cents,
    ADD COLUMN taux_tva_menu_basis_points INT UNSIGNED NULL AFTER prix_par_personne_snapshot_cents,
    ADD COLUMN taux_tva_livraison_basis_points INT UNSIGNED NULL AFTER taux_tva_menu_basis_points,
    ADD COLUMN taux_reduction_basis_points INT UNSIGNED NULL AFTER taux_tva_livraison_basis_points,
    ADD COLUMN remise_appliquee_cents BIGINT UNSIGNED NULL AFTER taux_reduction_basis_points,
    ADD COLUMN taux_tva_livraison_id INT NULL AFTER taux_tva_menu_id;
UPDATE commande_ligne
SET prix_menu_cents = CAST(ROUND(prix_menu * 100) AS UNSIGNED),
    prix_livraison_cents = CAST(ROUND(prix_livraison * 100) AS UNSIGNED),
    prix_total_ligne_cents = CAST(ROUND(prix_total_ligne * 100) AS UNSIGNED),
    prix_par_personne_snapshot_cents = CAST(ROUND(prix_par_personne_snapshot * 100) AS UNSIGNED),
    taux_tva_menu_basis_points = CAST(ROUND(taux_tva_snapshot * 100) AS UNSIGNED),
    -- Historical rows only had one tax snapshot. Reusing it for delivery is the only
    -- lossless interpretation available for pre-migration data; new rows persist both.
    taux_tva_livraison_basis_points = CAST(ROUND(taux_tva_snapshot * 100) AS UNSIGNED),
    taux_reduction_basis_points = CAST(ROUND(taux_reduction_snapshot * 100) AS UNSIGNED),
    remise_appliquee_cents = CAST(ROUND(remise_appliquee * 100) AS UNSIGNED),
    taux_tva_livraison_id = taux_tva_menu_id;
ALTER TABLE commande_ligne
    DROP COLUMN prix_menu,
    DROP COLUMN prix_livraison,
    DROP COLUMN prix_total_ligne,
    DROP COLUMN prix_par_personne_snapshot,
    DROP COLUMN taux_tva_snapshot,
    DROP COLUMN taux_reduction_snapshot,
    DROP COLUMN remise_appliquee,
    MODIFY prix_menu_cents BIGINT UNSIGNED NOT NULL,
    MODIFY prix_livraison_cents BIGINT UNSIGNED NOT NULL,
    MODIFY prix_total_ligne_cents BIGINT UNSIGNED NOT NULL,
    MODIFY prix_par_personne_snapshot_cents BIGINT UNSIGNED NOT NULL,
    MODIFY taux_tva_menu_basis_points INT UNSIGNED NOT NULL,
    MODIFY taux_tva_livraison_basis_points INT UNSIGNED NOT NULL,
    MODIFY taux_reduction_basis_points INT UNSIGNED NOT NULL,
    MODIFY remise_appliquee_cents BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT chk_commande_ligne_money_cents CHECK (
        prix_menu_cents >= 0 AND prix_livraison_cents >= 0 AND prix_total_ligne_cents >= 0
        AND prix_par_personne_snapshot_cents >= 0 AND remise_appliquee_cents >= 0
    ),
    ADD CONSTRAINT chk_commande_ligne_rates CHECK (
        taux_tva_menu_basis_points <= 10000
        AND taux_tva_livraison_basis_points <= 10000
        AND taux_reduction_basis_points <= 10000
    ),
    ADD CONSTRAINT fk_commande_ligne_tva_menu FOREIGN KEY (taux_tva_menu_id) REFERENCES taux_tva(taux_id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_commande_ligne_tva_livraison FOREIGN KEY (taux_tva_livraison_id) REFERENCES taux_tva(taux_id) ON DELETE SET NULL,
    ADD KEY idx_commande_ligne_tva_livraison (taux_tva_livraison_id);

SET @ddl = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE paiement DROP CHECK chk_paiement_montant',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'paiement'
      AND CONSTRAINT_NAME = 'chk_paiement_montant'
      AND CONSTRAINT_TYPE = 'CHECK'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

ALTER TABLE paiement
    ADD COLUMN montant_cents BIGINT UNSIGNED NULL AFTER nature;
UPDATE paiement
SET montant_cents = CAST(ROUND(montant * 100) AS UNSIGNED);
ALTER TABLE paiement
    DROP COLUMN montant,
    MODIFY montant_cents BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT chk_paiement_montant_cents CHECK (montant_cents > 0);

CREATE VIEW v_paiements_commande AS
SELECT
    p.commande_id,
    SUM(CASE WHEN p.nature = 'remboursement' THEN -CAST(p.montant_cents AS SIGNED) ELSE CAST(p.montant_cents AS SIGNED) END) AS total_encaisse_cents,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'acompte' THEN p.montant_cents ELSE 0 END) AS total_acomptes_cents,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'solde' THEN p.montant_cents ELSE 0 END) AS total_soldes_cents,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'paiement_unique' THEN p.montant_cents ELSE 0 END) AS total_paiements_uniques_cents,
    SUM(CASE WHEN p.nature = 'remboursement' THEN p.montant_cents ELSE 0 END) AS total_rembourse_cents,
    COUNT(p.paiement_id) AS nb_paiements,
    MAX(p.date_paiement) AS derniere_date_paiement
FROM paiement p
GROUP BY p.commande_id;

CREATE VIEW v_ca_stats AS
SELECT
    cl.ligne_id,
    cl.commande_id,
    c.numero_commande,
    c.currency,
    cl.menu_id,
    m.titre AS menu_titre,
    cl.nombre_personne,
    COALESCE(NULLIF(cl.prix_par_personne_snapshot_cents, 0) * cl.nombre_personne, cl.prix_menu_cents + cl.remise_appliquee_cents) AS prix_brut_menu_cents,
    cl.remise_appliquee_cents,
    cl.prix_menu_cents AS prix_net_menu_cents,
    cl.prix_livraison_cents,
    cl.prix_total_ligne_cents,
    cl.taux_tva_menu_basis_points,
    cl.taux_tva_livraison_basis_points,
    CAST(ROUND(cl.prix_menu_cents * 10000 / (10000 + cl.taux_tva_menu_basis_points)) AS SIGNED)
        + CAST(ROUND(cl.prix_livraison_cents * 10000 / (10000 + cl.taux_tva_livraison_basis_points)) AS SIGNED)
        AS prix_total_ligne_ht_cents,
    CAST(cl.prix_total_ligne_cents AS SIGNED)
        - CAST(ROUND(cl.prix_menu_cents * 10000 / (10000 + cl.taux_tva_menu_basis_points)) AS SIGNED)
        - CAST(ROUND(cl.prix_livraison_cents * 10000 / (10000 + cl.taux_tva_livraison_basis_points)) AS SIGNED)
        AS tva_ligne_cents,
    c.statut,
    c.date_commande,
    c.date_prestation,
    c.ville_livraison,
    c.code_postal_livraison,
    COALESCE(ch_accept.date_acceptation, c.date_commande) AS date_comptabilisation,
    u.prenom AS client_prenom,
    u.nom AS client_nom,
    u.email AS client_email
FROM commande c
JOIN commande_ligne cl ON cl.commande_id = c.commande_id
JOIN menu m ON m.menu_id = cl.menu_id
JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
LEFT JOIN (
    SELECT commande_id, MIN(created_at) AS date_acceptation
    FROM commande_historique
    WHERE nouveau_statut = 'accepte'
    GROUP BY commande_id
) ch_accept ON ch_accept.commande_id = c.commande_id
WHERE c.statut IN ('accepte','en_preparation','en_cours_livraison','livre','en_attente_materiel','terminee');

CREATE VIEW v_ca_commandes AS
SELECT
    c.commande_id,
    c.numero_commande,
    c.currency,
    c.statut,
    c.date_commande,
    c.date_prestation,
    c.ville_livraison,
    c.prix_total_cents AS total_ttc_cents,
    SUM(
        CAST(ROUND(cl.prix_menu_cents * 10000 / (10000 + cl.taux_tva_menu_basis_points)) AS SIGNED)
        + CAST(ROUND(cl.prix_livraison_cents * 10000 / (10000 + cl.taux_tva_livraison_basis_points)) AS SIGNED)
    ) AS total_ht_cents,
    SUM(
        CAST(cl.prix_total_ligne_cents AS SIGNED)
        - CAST(ROUND(cl.prix_menu_cents * 10000 / (10000 + cl.taux_tva_menu_basis_points)) AS SIGNED)
        - CAST(ROUND(cl.prix_livraison_cents * 10000 / (10000 + cl.taux_tva_livraison_basis_points)) AS SIGNED)
    ) AS total_tva_cents,
    COUNT(cl.ligne_id) AS nb_menus,
    SUM(cl.nombre_personne) AS nb_personnes,
    SUM(cl.prix_livraison_cents) AS frais_livraison_cents,
    COALESCE(ch_accept.date_acceptation, c.date_commande) AS date_comptabilisation,
    COALESCE(vpc.total_encaisse_cents, 0) AS montant_encaisse_cents,
    CAST(c.prix_total_cents AS SIGNED) - COALESCE(vpc.total_encaisse_cents, 0) AS solde_restant_cents,
    CASE
        WHEN COALESCE(vpc.total_encaisse_cents, 0) <= 0 THEN 'non_paye'
        WHEN CAST(c.prix_total_cents AS SIGNED) - COALESCE(vpc.total_encaisse_cents, 0) <= 0 THEN 'solde'
        ELSE 'acompte_verse'
    END AS statut_paiement,
    u.prenom AS client_prenom,
    u.nom AS client_nom,
    u.email AS client_email
FROM commande c
JOIN commande_ligne cl ON cl.commande_id = c.commande_id
JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
LEFT JOIN (
    SELECT commande_id, MIN(created_at) AS date_acceptation
    FROM commande_historique
    WHERE nouveau_statut = 'accepte'
    GROUP BY commande_id
) ch_accept ON ch_accept.commande_id = c.commande_id
LEFT JOIN v_paiements_commande vpc ON vpc.commande_id = c.commande_id
WHERE c.statut IN ('accepte','en_preparation','en_cours_livraison','livre','en_attente_materiel','terminee')
GROUP BY
    c.commande_id, c.numero_commande, c.currency, c.statut, c.date_commande, c.date_prestation,
    c.ville_livraison, c.prix_total_cents, ch_accept.date_acceptation, vpc.total_encaisse_cents,
    u.prenom, u.nom, u.email;

CREATE VIEW v_ca_mensuel AS
SELECT
    YEAR(date_comptabilisation) AS annee,
    MONTH(date_comptabilisation) AS mois,
    DATE_FORMAT(date_comptabilisation, '%Y-%m') AS annee_mois,
    currency,
    COUNT(DISTINCT commande_id) AS nb_commandes,
    SUM(total_ttc_cents) AS ca_ttc_cents,
    SUM(total_ht_cents) AS ca_ht_cents,
    SUM(total_tva_cents) AS tva_collectee_cents,
    SUM(nb_personnes) AS nb_personnes,
    CAST(ROUND(SUM(total_ttc_cents) / COUNT(DISTINCT commande_id)) AS SIGNED) AS panier_moyen_ttc_cents
FROM v_ca_commandes
GROUP BY YEAR(date_comptabilisation), MONTH(date_comptabilisation), DATE_FORMAT(date_comptabilisation, '%Y-%m'), currency;

CREATE VIEW v_ca_par_menu AS
SELECT
    s.menu_id,
    s.menu_titre,
    s.currency,
    COUNT(DISTINCT s.commande_id) AS nb_commandes,
    SUM(s.nombre_personne) AS nb_personnes,
    SUM(s.prix_net_menu_cents) AS ca_menu_ttc_cents,
    SUM(CAST(ROUND(s.prix_net_menu_cents * 10000 / (10000 + s.taux_tva_menu_basis_points)) AS SIGNED)) AS ca_menu_ht_cents,
    CAST(ROUND(AVG(s.prix_net_menu_cents)) AS SIGNED) AS prix_moyen_menu_cents,
    ROUND(AVG(s.nombre_personne), 1) AS nb_personnes_moyen
FROM v_ca_stats s
GROUP BY s.menu_id, s.menu_titre, s.currency;
