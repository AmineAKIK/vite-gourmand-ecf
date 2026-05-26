-- Migration 012 — Fondations financières
-- Corrige les types monétaires (DOUBLE → DECIMAL) sur commande et commande_ligne,
-- ajoute les colonnes de snapshot nécessaires pour reconstruire fidèlement les factures
-- sans dépendre des valeurs actuelles de la table menu.

SET NAMES utf8mb4;

-- ============================================================
-- 1. commande.prix_total : DOUBLE → DECIMAL(10,2)
-- ============================================================

ALTER TABLE commande
    MODIFY COLUMN prix_total DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- ============================================================
-- 2. commande_ligne : DOUBLE → DECIMAL + colonnes snapshot
-- ============================================================

ALTER TABLE commande_ligne
    MODIFY COLUMN prix_menu            DECIMAL(10,2) NOT NULL,
    MODIFY COLUMN prix_livraison       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN prix_total_ligne     DECIMAL(10,2) NOT NULL;

-- Prix unitaire au moment de la commande (indépendant des mises à jour du menu)
ALTER TABLE commande_ligne
    ADD COLUMN IF NOT EXISTS prix_par_personne_snapshot DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Prix par personne au moment de la commande (snapshot)';

-- Taux de TVA applicable à cette ligne au moment de la commande
ALTER TABLE commande_ligne
    ADD COLUMN IF NOT EXISTS taux_tva_snapshot DECIMAL(5,2) NOT NULL DEFAULT 10.00
        COMMENT 'Taux TVA applicable au moment de la commande';

-- Taux de réduction utilisé pour calculer prix_menu (0.00 si aucune réduction)
ALTER TABLE commande_ligne
    ADD COLUMN IF NOT EXISTS taux_reduction_snapshot DECIMAL(5,2) NOT NULL DEFAULT 0.00
        COMMENT 'Taux de réduction global appliqué sur le total commande (0.00 = pas de réduction)';

-- Montant de remise effectivement déduit sur cette ligne
-- (réduction globale répartie proportionnellement par ligne)
ALTER TABLE commande_ligne
    ADD COLUMN IF NOT EXISTS remise_appliquee DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Montant de remise déduit sur cette ligne (répartition proportionnelle de la remise globale)';

-- ============================================================
-- 3. menu.prix_par_personne : DOUBLE → DECIMAL(10,2)
--    (cohérence, même si le snapshot porte maintenant l'historique)
-- ============================================================

ALTER TABLE menu
    MODIFY COLUMN prix_par_personne DECIMAL(10,2) NOT NULL;

-- ============================================================
-- 4. Correction de l'incohérence livraison_km
--    config.php définit LIVRAISON_KM = 0.59 mais la DB contient '0.50'.
--    On aligne la DB sur la constante PHP qui est la valeur documentée.
-- ============================================================

UPDATE site_config SET valeur = '0.59' WHERE cle = 'livraison_km' AND valeur = '0.50';

-- ============================================================
-- 5. Backfill des lignes existantes : renseigner les snapshots
--    pour les commandes déjà créées (best-effort depuis les données actuelles)
-- ============================================================

UPDATE commande_ligne cl
JOIN menu m ON m.menu_id = cl.menu_id
SET
    cl.prix_par_personne_snapshot = m.prix_par_personne,
    cl.taux_tva_snapshot          = 10.00,
    cl.taux_reduction_snapshot    = 0.00,
    cl.remise_appliquee           = 0.00
WHERE cl.prix_par_personne_snapshot = 0.00;
