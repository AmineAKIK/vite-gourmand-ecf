-- Migration 014 — Système de paiements
-- Permet de tracer chaque encaissement (acompte, solde, paiement unique)
-- et de lier les paiements aux documents de facturation correspondants.
-- Supporte : virement, chèque, espèces, CB en ligne.

SET NAMES utf8mb4;

-- ============================================================
-- 1. Modes de paiement acceptés (référentiel configurable)
-- ============================================================

CREATE TABLE IF NOT EXISTS mode_paiement (
    mode_id  INT AUTO_INCREMENT PRIMARY KEY,
    libelle  VARCHAR(60)  NOT NULL,
    code     VARCHAR(30)  NOT NULL,
    actif    TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uk_mode_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO mode_paiement (libelle, code, actif) VALUES
    ('Virement bancaire',       'virement',   1),
    ('Chèque',                  'cheque',     1),
    ('Espèces',                 'especes',    1),
    ('Carte bancaire en ligne', 'cb_online',  0);

-- ============================================================
-- 2. Table des paiements reçus
-- ============================================================

CREATE TABLE IF NOT EXISTS paiement (
    paiement_id   INT AUTO_INCREMENT PRIMARY KEY,
    commande_id   INT          NOT NULL,
    -- Référence optionnelle au document qui justifie ce paiement
    -- (ex: la facture d'acompte FAC-2025-0001)
    document_id   INT          DEFAULT NULL,
    -- Type : 'acompte' (avant prestation), 'solde' (après), 'paiement_unique' (tout en une fois)
    type_paiement ENUM('acompte', 'solde', 'paiement_unique') NOT NULL,
    montant       DECIMAL(10,2) NOT NULL,
    mode          VARCHAR(30)  NOT NULL,            -- code de mode_paiement
    date_paiement DATE         NOT NULL,
    -- Référence traçable : numéro de virement, numéro de chèque, etc.
    reference     VARCHAR(100) DEFAULT NULL,
    note          TEXT         DEFAULT NULL,
    cree_par      INT          DEFAULT NULL,
    cree_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    FOREIGN KEY (document_id) REFERENCES document_facturation(document_id) ON DELETE SET NULL,
    FOREIGN KEY (cree_par)    REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,

    INDEX idx_paiement_commande (commande_id),
    INDEX idx_paiement_document (document_id),
    INDEX idx_paiement_date     (date_paiement),
    INDEX idx_paiement_mode     (mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Extension de document_facturation pour les acomptes
--    Permet à une facture d'indiquer l'acompte déjà versé
--    et le solde restant à régler.
-- ============================================================

ALTER TABLE document_facturation
    -- Type étendu : acompte = facture émise avant prestation pour percevoir un acompte
    MODIFY COLUMN type_document ENUM('facture', 'ticket', 'acompte') NOT NULL;

ALTER TABLE document_facturation
    ADD COLUMN IF NOT EXISTS montant_acompte_verse DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Somme des acomptes déjà perçus (déduite du total sur la facture de solde)',
    ADD COLUMN IF NOT EXISTS solde_a_regler DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Montant restant dû = total_ttc - montant_acompte_verse';

-- ============================================================
-- 4. Séquence de numérotation pour les factures d'acompte
--    Le type 'acompte' utilisera le préfixe 'ACP-YYYY-NNNN'
-- ============================================================

-- Aucune modification nécessaire sur document_sequence :
-- la table (type_document, annee) accueille nativement 'acompte' comme nouveau type.

-- ============================================================
-- 5. Vue de synthèse des paiements par commande
--    Utilisée par PaiementModel pour calculer le solde restant.
-- ============================================================

CREATE OR REPLACE VIEW v_paiements_commande AS
SELECT
    p.commande_id,
    SUM(p.montant)                                                       AS total_encaisse,
    SUM(CASE WHEN p.type_paiement = 'acompte'        THEN p.montant ELSE 0 END) AS total_acomptes,
    SUM(CASE WHEN p.type_paiement = 'solde'          THEN p.montant ELSE 0 END) AS total_soldes,
    SUM(CASE WHEN p.type_paiement = 'paiement_unique' THEN p.montant ELSE 0 END) AS total_paiements_uniques,
    COUNT(p.paiement_id)                                                 AS nb_paiements,
    MAX(p.date_paiement)                                                 AS derniere_date_paiement
FROM paiement p
GROUP BY p.commande_id;
