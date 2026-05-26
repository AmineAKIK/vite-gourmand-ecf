-- Migration 015 — Taux de TVA configurables
-- Remplace la constante hardcodée DEFAULT_TVA = 10.0 dans FacturationModel.
-- Le traiteur peut ajouter, modifier ou désactiver des taux via l'interface admin
-- sans aucune intervention développeur si la loi change.
--
-- Taux français traiteur/restauration en vigueur (2025) :
--   10% — Restauration, traiteur, repas à emporter (art. 278bis CGI)
--   20% — Alcools, tabac, produits non alimentaires
--    0% — Non assujetti TVA (micro-entreprise art. 293B CGI)

SET NAMES utf8mb4;

-- ============================================================
-- 1. Table des taux de TVA
-- ============================================================

CREATE TABLE IF NOT EXISTS taux_tva (
    taux_id      INT AUTO_INCREMENT PRIMARY KEY,
    libelle      VARCHAR(80)  NOT NULL,
    taux         DECIMAL(5,2) NOT NULL,
    -- Catégorie pour guider l'utilisateur dans l'interface
    -- 'menu' : applicable aux lignes de menu/prestation
    -- 'livraison' : applicable aux frais de livraison
    -- 'general' : taux générique utilisable sur toute ligne
    categorie    ENUM('menu', 'livraison', 'general') NOT NULL DEFAULT 'general',
    actif        TINYINT(1)   NOT NULL DEFAULT 1,
    -- Un seul taux par défaut par catégorie.
    -- Utilisé à la création automatique d'un document depuis une commande.
    par_defaut   TINYINT(1)   NOT NULL DEFAULT 0,
    note         VARCHAR(255) DEFAULT NULL,     -- référence légale, contexte

    INDEX idx_taux_tva_actif      (actif),
    INDEX idx_taux_tva_categorie  (categorie),
    -- Contrainte soft : on ne peut pas avoir 2 taux par_defaut=1 pour la même catégorie.
    -- Appliquée par PricingService (et non en DB pour éviter les transactions complexes).
    INDEX idx_taux_tva_defaut     (par_defaut, categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Données initiales — taux français 2025
-- ============================================================

INSERT IGNORE INTO taux_tva (libelle, taux, categorie, actif, par_defaut, note) VALUES
    -- Taux réduit restauration/traiteur (art. 278bis CGI)
    ('Restauration et traiteur – 10%',         10.00, 'menu',      1, 1,
     'Art. 278bis CGI – repas à emporter, traiteur, restauration assise'),

    -- Livraison : même taux que la prestation principale (10%)
    ('Livraison de repas – 10%',               10.00, 'livraison', 1, 1,
     'Art. 278bis CGI – livraison de repas liée à la prestation traiteur'),

    -- Taux normal pour produits non alimentaires ou alcools
    ('Taux normal – 20%',                      20.00, 'general',   1, 0,
     'Art. 278 CGI – taux général (alcools, produits hors alimentation)'),

    -- Taux spécial pour produits alimentaires de base (épicerie, non transformés)
    ('Produits alimentaires de base – 5,5%',    5.50, 'general',   1, 0,
     'Art. 278-0bis CGI – produits destinés à l''alimentation humaine (non transformés)'),

    -- Taux zéro pour non-assujettis (micro-entreprise)
    -- Désactivé par défaut : s'active si regime_tva = 'non_assujetti'
    ('Non assujetti à la TVA – 0%',             0.00, 'general',   0, 0,
     'Art. 293B CGI – micro-entreprise en franchise de base de TVA');

-- ============================================================
-- 3. Lier document_facturation_ligne à taux_tva
--    Colonne référence optionnelle (les anciens documents n'ont pas de taux_id)
-- ============================================================

ALTER TABLE document_facturation_ligne
    ADD COLUMN IF NOT EXISTS taux_tva_id INT DEFAULT NULL
        COMMENT 'Référence au taux_tva utilisé (NULL pour les documents créés avant migration 015)',
    ADD CONSTRAINT fk_doc_ligne_taux_tva
        FOREIGN KEY (taux_tva_id) REFERENCES taux_tva(taux_id) ON DELETE SET NULL;

-- ============================================================
-- 4. Lier commande_ligne à taux_tva
--    Permet à PricingService de stocker le taux_id utilisé au checkout
-- ============================================================

ALTER TABLE commande_ligne
    ADD COLUMN IF NOT EXISTS taux_tva_id INT DEFAULT NULL
        COMMENT 'Référence au taux_tva utilisé au moment de la commande',
    ADD CONSTRAINT fk_commande_ligne_taux_tva
        FOREIGN KEY (taux_tva_id) REFERENCES taux_tva(taux_id) ON DELETE SET NULL;
