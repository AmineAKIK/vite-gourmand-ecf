-- Migration 006 — Système de panier : introduction de commande_ligne
-- La table commande perd menu_id/nombre_personne/prix_menu/prix_livraison (colonne).
-- Ces données sont maintenant dans commande_ligne (N lignes par commande).
-- prix_total sur commande reste : c'est la somme de toutes les lignes.
-- Les commandes existantes (1 menu = 1 ligne) sont migrées automatiquement.

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. Créer la table commande_ligne
-- ============================================================

CREATE TABLE commande_ligne (
    ligne_id          INT AUTO_INCREMENT PRIMARY KEY,
    commande_id       INT NOT NULL,
    menu_id           INT NOT NULL,
    nombre_personne   INT NOT NULL,
    prix_menu         DOUBLE NOT NULL,
    prix_livraison    DOUBLE NOT NULL DEFAULT 0,
    prix_total_ligne  DOUBLE NOT NULL,
    FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id)     REFERENCES menu(menu_id)
);

CREATE INDEX idx_commande_ligne_commande ON commande_ligne(commande_id);
CREATE INDEX idx_commande_ligne_menu     ON commande_ligne(menu_id);

-- ============================================================
-- 2. Migrer les commandes existantes (1 commande → 1 ligne)
-- ============================================================

INSERT INTO commande_ligne (commande_id, menu_id, nombre_personne, prix_menu, prix_livraison, prix_total_ligne)
SELECT commande_id, menu_id, nombre_personne, prix_menu, prix_livraison, prix_total
FROM commande;

-- ============================================================
-- 3. Supprimer l'index qui dépend de menu_id avant le DROP
-- ============================================================

DROP INDEX idx_commande_menu_statut ON commande;

-- ============================================================
-- 4. Supprimer les colonnes déplacées dans commande_ligne
-- ============================================================

ALTER TABLE commande
    DROP COLUMN menu_id,
    DROP COLUMN nombre_personne,
    DROP COLUMN prix_menu,
    DROP COLUMN prix_livraison;

-- prix_total reste sur commande (somme des lignes, calculé à la création)

SET FOREIGN_KEY_CHECKS = 1;
