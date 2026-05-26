-- Migration 008 — Brouillons de factures et tickets de caisse

CREATE TABLE IF NOT EXISTS document_facturation (
    document_id          INT AUTO_INCREMENT PRIMARY KEY,
    commande_id          INT NOT NULL,
    type_document        VARCHAR(20) NOT NULL,
    statut               VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    numero_document      VARCHAR(50),
    date_emission        DATE NOT NULL,
    date_prestation      DATE,
    client_nom           VARCHAR(160) NOT NULL DEFAULT '',
    client_email         VARCHAR(190) NOT NULL DEFAULT '',
    client_telephone     VARCHAR(40) NOT NULL DEFAULT '',
    client_adresse       VARCHAR(255) NOT NULL DEFAULT '',
    client_ville         VARCHAR(120) NOT NULL DEFAULT '',
    client_code_postal   VARCHAR(20) NOT NULL DEFAULT '',
    entreprise_snapshot  LONGTEXT,
    note_publique        TEXT,
    mention_legale       TEXT,
    total_ht             DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_tva            DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_ttc            DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_by           INT,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utilisateur(utilisateur_id),
    INDEX idx_document_facturation_commande (commande_id),
    INDEX idx_document_facturation_type (type_document),
    INDEX idx_document_facturation_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_facturation_ligne (
    ligne_document_id    INT AUTO_INCREMENT PRIMARY KEY,
    document_id          INT NOT NULL,
    designation          VARCHAR(255) NOT NULL,
    quantite             DECIMAL(10,2) NOT NULL DEFAULT 1,
    prix_unitaire_ht     DECIMAL(10,2) NOT NULL DEFAULT 0,
    prix_unitaire_ttc    DECIMAL(10,2) NOT NULL DEFAULT 0,
    taux_tva             DECIMAL(5,2) NOT NULL DEFAULT 10,
    total_ht             DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_tva            DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_ttc            DECIMAL(10,2) NOT NULL DEFAULT 0,
    ordre                INT NOT NULL DEFAULT 0,
    FOREIGN KEY (document_id) REFERENCES document_facturation(document_id) ON DELETE CASCADE,
    INDEX idx_document_facturation_ligne_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
