-- Tugères V1 — canonical fresh-install database baseline
--
-- Contract:
--   * MySQL 8.4 is the reference engine for V1.
--   * This file describes a NEW, EMPTY database only.
--   * It contains the final schema directly; no pre-release transition is replayed.
--   * Tenant branding, catalogue/demo content and commercial policy values are NOT seeded here.
--   * Product/market reference data is seeded only when stable identifiers are required by the runtime.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- Identity / access
-- -----------------------------------------------------------------------------

CREATE TABLE role (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL,
    CONSTRAINT uk_role_libelle UNIQUE (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role ids are part of the current application contract (1 client, 2 employee, 3 admin).
INSERT INTO role (role_id, libelle) VALUES
    (1, 'utilisateur'),
    (2, 'employe'),
    (3, 'administrateur');

CREATE TABLE utilisateur (
    utilisateur_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) NULL,
    adresse VARCHAR(255) NULL,
    ville VARCHAR(100) NULL,
    code_postal VARCHAR(20) NULL,
    pays VARCHAR(100) NULL,
    role_id INT NOT NULL DEFAULT 1,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    email_verified_at DATETIME NULL,
    email_verification_token VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_utilisateur_email UNIQUE (email),
    CONSTRAINT uk_utilisateur_email_verification_token UNIQUE (email_verification_token),
    CONSTRAINT fk_utilisateur_role FOREIGN KEY (role_id) REFERENCES role(role_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_password_reset_token UNIQUE (token),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE CASCADE,
    KEY idx_password_reset_expiry (expires_at, used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT NOT NULL DEFAULT 1,
    last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blocked_until DATETIME NULL,
    CONSTRAINT uk_rate_limit_ip_action UNIQUE (ip, action),
    KEY idx_rate_limit_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tenant configuration / presentation containers
-- Values are deliberately empty: Phase 2 will define the typed configuration registry.
-- -----------------------------------------------------------------------------

CREATE TABLE site_config (
    cle VARCHAR(80) NOT NULL PRIMARY KEY,
    valeur VARCHAR(500) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_image (
    cle VARCHAR(50) PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horaire (
    horaire_id INT AUTO_INCREMENT PRIMARY KEY,
    jour VARCHAR(20) NOT NULL,
    heure_ouverture VARCHAR(10) NULL,
    heure_fermeture VARCHAR(10) NULL,
    CONSTRAINT uk_horaire_jour UNIQUE (jour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Catalogue
-- -----------------------------------------------------------------------------

CREATE TABLE regime (
    regime_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(80) NOT NULL,
    CONSTRAINT uk_regime_libelle UNIQUE (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE theme (
    theme_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(80) NOT NULL,
    CONSTRAINT uk_theme_libelle UNIQUE (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorie_plat (
    categorie_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(80) NOT NULL,
    CONSTRAINT uk_categorie_plat_libelle UNIQUE (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Neutral structural categories used by the current UI. No menu or tenant content is seeded.
INSERT INTO categorie_plat (categorie_id, libelle) VALUES
    (1, 'Entrée'),
    (2, 'Plat principal'),
    (3, 'Dessert');

CREATE TABLE menu (
    menu_id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(100) NOT NULL,
    description TEXT NULL,
    nombre_personne_minimum INT NOT NULL DEFAULT 2,
    prix_par_personne DECIMAL(10,2) NOT NULL,
    quantite_restante INT NULL,
    conditions TEXT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    theme_id INT NULL,
    regime_id INT NULL,
    CONSTRAINT chk_menu_minimum CHECK (nombre_personne_minimum >= 1),
    CONSTRAINT chk_menu_prix CHECK (prix_par_personne >= 0),
    CONSTRAINT chk_menu_quantite CHECK (quantite_restante IS NULL OR quantite_restante >= 0),
    CONSTRAINT fk_menu_theme FOREIGN KEY (theme_id) REFERENCES theme(theme_id) ON DELETE SET NULL,
    CONSTRAINT fk_menu_regime FOREIGN KEY (regime_id) REFERENCES regime(regime_id) ON DELETE SET NULL,
    KEY idx_menu_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_image (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    chemin VARCHAR(500) NOT NULL,
    ordre INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_menu_image_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
    KEY idx_menu_image_menu (menu_id, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plat (
    plat_id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(100) NOT NULL,
    categorie_id INT NOT NULL,
    CONSTRAINT fk_plat_categorie FOREIGN KEY (categorie_id) REFERENCES categorie_plat(categorie_id) ON DELETE RESTRICT,
    KEY idx_plat_categorie (categorie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_plat (
    menu_id INT NOT NULL,
    plat_id INT NOT NULL,
    PRIMARY KEY (menu_id, plat_id),
    CONSTRAINT fk_menu_plat_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_plat_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE allergen (
    allergen_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    libelle VARCHAR(80) NOT NULL,
    emoji VARCHAR(16) NOT NULL DEFAULT '',
    ordre TINYINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT uk_allergen_code UNIQUE (code),
    CONSTRAINT uk_allergen_ordre UNIQUE (ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regulatory reference set for the France/EU market profile (INCO 1169/2011).
INSERT INTO allergen (allergen_id, code, libelle, emoji, ordre) VALUES
    (1,  'gluten',       'Céréales contenant du gluten',  '🌾', 1),
    (2,  'crustaces',    'Crustacés',                     '🦀', 2),
    (3,  'oeufs',        'Œufs',                          '🥚', 3),
    (4,  'poissons',     'Poissons',                      '🐟', 4),
    (5,  'arachides',    'Arachides',                     '🥜', 5),
    (6,  'soja',         'Soja',                          '🫘', 6),
    (7,  'lait',         'Lait',                          '🥛', 7),
    (8,  'fruits_coque', 'Fruits à coque',                '🌰', 8),
    (9,  'celeri',       'Céleri',                        '🥬', 9),
    (10, 'moutarde',     'Moutarde',                      '🌿', 10),
    (11, 'sesame',       'Graines de sésame',             '🌱', 11),
    (12, 'so2',          'Dioxyde de soufre et sulfites', '🧪', 12),
    (13, 'lupin',        'Lupin',                         '🌸', 13),
    (14, 'mollusques',   'Mollusques',                    '🐚', 14);

CREATE TABLE plat_allergen (
    plat_id INT NOT NULL,
    allergen_id INT NOT NULL,
    PRIMARY KEY (plat_id, allergen_id),
    CONSTRAINT fk_plat_allergen_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE,
    CONSTRAINT fk_plat_allergen_allergen FOREIGN KEY (allergen_id) REFERENCES allergen(allergen_id) ON DELETE RESTRICT,
    KEY idx_plat_allergen_allergen (allergen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tax / payment capability registries
-- Deliberately no tenant policy rows are enabled/seeded here.
-- -----------------------------------------------------------------------------

CREATE TABLE taux_tva (
    taux_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(80) NOT NULL,
    taux DECIMAL(5,2) NOT NULL,
    categorie ENUM('menu','livraison','general') NOT NULL DEFAULT 'general',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    par_defaut TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    CONSTRAINT chk_taux_tva_value CHECK (taux >= 0 AND taux <= 100),
    KEY idx_taux_tva_actif (actif),
    KEY idx_taux_tva_categorie (categorie),
    KEY idx_taux_tva_defaut (par_defaut, categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mode_paiement (
    mode_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(60) NOT NULL,
    code VARCHAR(30) NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT uk_mode_paiement_code UNIQUE (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Orders
-- -----------------------------------------------------------------------------

CREATE TABLE commande (
    commande_id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) NOT NULL,
    utilisateur_id INT NOT NULL,
    date_commande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_prestation DATE NOT NULL,
    heure_livraison VARCHAR(10) NOT NULL,
    adresse_livraison VARCHAR(255) NOT NULL,
    ville_livraison VARCHAR(100) NOT NULL,
    code_postal_livraison VARCHAR(20) NOT NULL,
    prix_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    pret_materiel TINYINT(1) NOT NULL DEFAULT 0,
    motif_annulation TEXT NULL,
    mode_contact_annulation VARCHAR(50) NULL,
    instructions TEXT NULL,
    CONSTRAINT uk_commande_numero UNIQUE (numero_commande),
    CONSTRAINT chk_commande_prix_total CHECK (prix_total >= 0),
    CONSTRAINT fk_commande_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    KEY idx_commande_statut (statut),
    KEY idx_commande_utilisateur (utilisateur_id),
    KEY idx_commande_date_prestation (date_prestation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commande_ligne (
    ligne_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    menu_id INT NOT NULL,
    nombre_personne INT NOT NULL,
    prix_menu DECIMAL(10,2) NOT NULL,
    prix_livraison DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prix_total_ligne DECIMAL(10,2) NOT NULL,
    prix_par_personne_snapshot DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    taux_tva_snapshot DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    taux_reduction_snapshot DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    remise_appliquee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    taux_tva_id INT NULL,
    CONSTRAINT chk_commande_ligne_personnes CHECK (nombre_personne >= 1),
    CONSTRAINT chk_commande_ligne_montants CHECK (
        prix_menu >= 0 AND prix_livraison >= 0 AND prix_total_ligne >= 0
        AND prix_par_personne_snapshot >= 0 AND remise_appliquee >= 0
    ),
    CONSTRAINT fk_commande_ligne_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_ligne_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE RESTRICT,
    CONSTRAINT fk_commande_ligne_taux_tva FOREIGN KEY (taux_tva_id) REFERENCES taux_tva(taux_id) ON DELETE SET NULL,
    KEY idx_commande_ligne_commande (commande_id),
    KEY idx_commande_ligne_menu (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commande_historique (
    historique_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    ancien_statut VARCHAR(50) NULL,
    nouveau_statut VARCHAR(50) NOT NULL,
    commentaire TEXT NULL,
    modifie_par INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_commande_historique_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_historique_user FOREIGN KEY (modifie_par) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,
    KEY idx_historique_commande (commande_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Reviews / internal notifications
-- -----------------------------------------------------------------------------

CREATE TABLE avis (
    avis_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    note INT NOT NULL,
    description VARCHAR(300) NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    afficher_accueil TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_avis_commande UNIQUE (commande_id),
    CONSTRAINT chk_avis_note CHECK (note BETWEEN 1 AND 5),
    CONSTRAINT fk_avis_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    CONSTRAINT fk_avis_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    KEY idx_avis_statut (statut),
    KEY idx_avis_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    corps TEXT NULL,
    lu TINYINT(1) NOT NULL DEFAULT 0,
    commande_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    KEY idx_notif_user_lu (utilisateur_id, lu),
    KEY idx_notif_commande (commande_id),
    KEY idx_notification_retention (lu, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Recipes / inventory ledger
-- -----------------------------------------------------------------------------

CREATE TABLE ingredient (
    ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(100) NOT NULL,
    unite VARCHAR(20) NOT NULL DEFAULT 'kg',
    prix_unitaire DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    seuil_alerte DECIMAL(10,3) NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_ingredient_prix CHECK (prix_unitaire >= 0),
    CONSTRAINT chk_ingredient_seuil CHECK (seuil_alerte IS NULL OR seuil_alerte >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recette_ligne (
    recette_ligne_id INT AUTO_INCREMENT PRIMARY KEY,
    plat_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    grammage DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    CONSTRAINT uk_recette_plat_ingredient UNIQUE (plat_id, ingredient_id),
    CONSTRAINT chk_recette_grammage CHECK (grammage >= 0),
    CONSTRAINT fk_recette_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE,
    CONSTRAINT fk_recette_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredient(ingredient_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mouvement_stock (
    mouvement_id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    type_mouvement ENUM('entree','sortie','ajustement') NOT NULL,
    quantite DECIMAL(10,3) NOT NULL,
    motif VARCHAR(200) NULL,
    commande_id INT NULL,
    cree_par INT NULL,
    operation_key VARCHAR(191) NULL,
    reversal_of_mouvement_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_mouvement_stock_operation UNIQUE (operation_key),
    CONSTRAINT uk_mouvement_stock_reversal UNIQUE (reversal_of_mouvement_id),
    CONSTRAINT chk_mouvement_quantite CHECK (quantite > 0),
    CONSTRAINT fk_mouvement_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredient(ingredient_id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_user FOREIGN KEY (cree_par) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,
    CONSTRAINT fk_mouvement_reversal FOREIGN KEY (reversal_of_mouvement_id) REFERENCES mouvement_stock(mouvement_id) ON DELETE RESTRICT,
    KEY idx_mouvement_ingredient_date (ingredient_id, created_at),
    KEY idx_mouvement_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commande_ingredient_snapshot (
    commande_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantite DECIMAL(12,3) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (commande_id, ingredient_id),
    CONSTRAINT chk_commande_ingredient_quantite CHECK (quantite >= 0),
    CONSTRAINT fk_commande_ingredient_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    CONSTRAINT fk_commande_ingredient_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredient(ingredient_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Billing documents
-- -----------------------------------------------------------------------------

CREATE TABLE document_facturation (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    type_document ENUM('facture','ticket','devis','acompte','avoir') NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    numero_document VARCHAR(50) NULL,
    date_emission DATE NOT NULL,
    date_prestation DATE NULL,
    client_nom VARCHAR(160) NOT NULL DEFAULT '',
    client_email VARCHAR(190) NOT NULL DEFAULT '',
    client_telephone VARCHAR(40) NOT NULL DEFAULT '',
    client_adresse VARCHAR(255) NOT NULL DEFAULT '',
    client_ville VARCHAR(120) NOT NULL DEFAULT '',
    client_code_postal VARCHAR(20) NOT NULL DEFAULT '',
    client_siren VARCHAR(20) NULL,
    adresse_livraison VARCHAR(255) NULL,
    ville_livraison VARCHAR(120) NULL,
    code_postal_livraison VARCHAR(20) NULL,
    categorie_operation VARCHAR(30) NOT NULL DEFAULT 'mixte',
    option_tva_debits TINYINT(1) NOT NULL DEFAULT 0,
    entreprise_snapshot LONGTEXT NULL,
    note_publique TEXT NULL,
    mention_legale TEXT NULL,
    total_ht DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_tva DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_ttc DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    montant_acompte_verse DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    solde_a_regler DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    document_acompte_id INT NULL,
    source_document_id INT NULL,
    statut_devis ENUM('accepte','refuse') NULL,
    date_decision_devis DATETIME NULL,
    token_signature VARCHAR(64) NULL,
    signature_token_hash CHAR(64) NULL,
    signature_expires_at DATETIME NULL,
    signed_at DATETIME NULL,
    signed_ip VARCHAR(45) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    finalized_at DATETIME NULL,
    finalized_by INT NULL,
    archive_path VARCHAR(255) NULL,
    archive_status ENUM('pending','ready','failed') NULL,
    archive_last_error VARCHAR(500) NULL,
    archived_at DATETIME NULL,
    pdf_path VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    sent_by INT NULL,
    CONSTRAINT uk_document_numero UNIQUE (numero_document),
    CONSTRAINT uk_document_source_avoir UNIQUE (source_document_id, type_document),
    CONSTRAINT uk_document_signature_hash UNIQUE (signature_token_hash),
    CONSTRAINT chk_document_totaux CHECK (total_ht >= 0 AND total_tva >= 0 AND total_ttc >= 0),
    CONSTRAINT fk_document_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    CONSTRAINT fk_document_created_by FOREIGN KEY (created_by) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,
    CONSTRAINT fk_document_finalized_by FOREIGN KEY (finalized_by) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,
    CONSTRAINT fk_document_sent_by FOREIGN KEY (sent_by) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,
    CONSTRAINT fk_document_acompte FOREIGN KEY (document_acompte_id) REFERENCES document_facturation(document_id) ON DELETE SET NULL,
    CONSTRAINT fk_document_source FOREIGN KEY (source_document_id) REFERENCES document_facturation(document_id) ON DELETE RESTRICT,
    KEY idx_document_facturation_commande (commande_id),
    KEY idx_document_facturation_type (type_document),
    KEY idx_document_facturation_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_facturation_ligne (
    ligne_document_id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    designation VARCHAR(255) NOT NULL,
    quantite DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    prix_unitaire_ht DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prix_unitaire_ttc DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    taux_tva DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    taux_tva_id INT NULL,
    total_ht DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_tva DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_ttc DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ordre INT NOT NULL DEFAULT 0,
    CONSTRAINT chk_document_ligne_quantite CHECK (quantite > 0),
    CONSTRAINT chk_document_ligne_totaux CHECK (total_ht >= 0 AND total_tva >= 0 AND total_ttc >= 0),
    CONSTRAINT fk_document_ligne_document FOREIGN KEY (document_id) REFERENCES document_facturation(document_id) ON DELETE CASCADE,
    CONSTRAINT fk_document_ligne_taux_tva FOREIGN KEY (taux_tva_id) REFERENCES taux_tva(taux_id) ON DELETE SET NULL,
    KEY idx_document_ligne_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_sequence (
    type_document VARCHAR(20) NOT NULL,
    annee INT NOT NULL,
    dernier_numero INT NOT NULL DEFAULT 0,
    PRIMARY KEY (type_document, annee),
    CONSTRAINT chk_document_sequence_numero CHECK (dernier_numero >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Payment ledger / durable payment intents
-- -----------------------------------------------------------------------------

CREATE TABLE paiement (
    paiement_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    document_id INT NULL,
    type_paiement ENUM('acompte','solde','paiement_unique') NOT NULL,
    nature ENUM('encaissement','remboursement') NOT NULL DEFAULT 'encaissement',
    montant DECIMAL(10,2) NOT NULL,
    mode VARCHAR(30) NOT NULL,
    date_paiement DATE NOT NULL,
    reference VARCHAR(100) NULL,
    note TEXT NULL,
    operation_key VARCHAR(160) NULL,
    reversal_of_paiement_id INT NULL,
    cree_par INT NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_paiement_operation_key UNIQUE (operation_key),
    CONSTRAINT uk_paiement_reversal UNIQUE (reversal_of_paiement_id),
    CONSTRAINT chk_paiement_montant CHECK (montant > 0),
    CONSTRAINT fk_paiement_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    CONSTRAINT fk_paiement_document FOREIGN KEY (document_id) REFERENCES document_facturation(document_id) ON DELETE SET NULL,
    CONSTRAINT fk_paiement_user FOREIGN KEY (cree_par) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,
    CONSTRAINT fk_paiement_reversal FOREIGN KEY (reversal_of_paiement_id) REFERENCES paiement(paiement_id) ON DELETE RESTRICT,
    KEY idx_paiement_commande (commande_id),
    KEY idx_paiement_document (document_id),
    KEY idx_paiement_date (date_paiement),
    KEY idx_paiement_mode (mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_draft (
    draft_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) NOT NULL,
    utilisateur_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
    currency CHAR(3) NOT NULL DEFAULT 'eur',
    expected_total_cents BIGINT UNSIGNED NOT NULL,
    commande_snapshot JSON NOT NULL,
    pricing_snapshot JSON NOT NULL,
    panier_snapshot JSON NOT NULL,
    commande_id INT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uk_order_draft_numero UNIQUE (numero_commande),
    CONSTRAINT uk_order_draft_commande UNIQUE (commande_id),
    CONSTRAINT fk_order_draft_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_draft_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE SET NULL,
    KEY idx_order_draft_user_status (utilisateur_id, status),
    KEY idx_order_draft_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_attempt (
    attempt_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    draft_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'stripe',
    status VARCHAR(30) NOT NULL DEFAULT 'created',
    expected_amount_cents BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'eur',
    provider_session_id VARCHAR(255) NULL,
    provider_payment_intent_id VARCHAR(255) NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uk_payment_attempt_session UNIQUE (provider_session_id),
    CONSTRAINT uk_payment_attempt_intent UNIQUE (provider_payment_intent_id),
    CONSTRAINT fk_payment_attempt_draft FOREIGN KEY (draft_id) REFERENCES order_draft(draft_id) ON DELETE RESTRICT,
    KEY idx_payment_attempt_draft (draft_id),
    KEY idx_payment_attempt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stripe_webhook_event (
    event_id VARCHAR(255) PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    object_id VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'processing',
    last_error TEXT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_stripe_webhook_event_status (status),
    KEY idx_stripe_webhook_event_object (object_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_refund_attempt (
    refund_attempt_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paiement_id INT NOT NULL,
    commande_id INT NOT NULL,
    operation_key VARCHAR(160) NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_payment_reference VARCHAR(191) NOT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    status ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    provider_refund_id VARCHAR(191) NULL,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uk_refund_operation UNIQUE (operation_key),
    CONSTRAINT uk_refund_paiement UNIQUE (paiement_id),
    CONSTRAINT fk_refund_paiement FOREIGN KEY (paiement_id) REFERENCES paiement(paiement_id) ON DELETE RESTRICT,
    CONSTRAINT fk_refund_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    KEY idx_refund_commande (commande_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_cancellation_effect (
    commande_id INT PRIMARY KEY,
    menu_stock_restored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_cancellation_effect_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Atomic order admission / capacity reservation infrastructure
-- -----------------------------------------------------------------------------

CREATE TABLE order_admission_lock (
    scope_key VARCHAR(64) PRIMARY KEY,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_admission_reservation (
    reservation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) NOT NULL,
    date_prestation DATE NOT NULL,
    month_key CHAR(7) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'reserved',
    draft_id BIGINT UNSIGNED NULL,
    commande_id INT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uk_order_admission_numero UNIQUE (numero_commande),
    CONSTRAINT uk_order_admission_draft UNIQUE (draft_id),
    CONSTRAINT uk_order_admission_commande UNIQUE (commande_id),
    CONSTRAINT fk_order_admission_draft FOREIGN KEY (draft_id) REFERENCES order_draft(draft_id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_admission_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    KEY idx_order_admission_day (date_prestation, status, expires_at),
    KEY idx_order_admission_month (month_key, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Reminder execution ledger
-- -----------------------------------------------------------------------------

CREATE TABLE cron_rappel_log (
    rappel_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    type_rappel VARCHAR(40) NOT NULL,
    date_cible DATE NOT NULL,
    lease_token CHAR(32) NULL,
    lease_until DATETIME NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    CONSTRAINT uk_cron_rappel_once UNIQUE (commande_id, type_rappel, date_cible),
    CONSTRAINT fk_cron_rappel_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    KEY idx_cron_rappel_created (created_at),
    KEY idx_cron_rappel_pending (sent_at, lease_until, created_at),
    KEY idx_cron_rappel_retention (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Read models / reporting views
-- -----------------------------------------------------------------------------

CREATE VIEW v_paiements_commande AS
SELECT
    p.commande_id,
    SUM(CASE WHEN p.nature = 'remboursement' THEN -p.montant ELSE p.montant END) AS total_encaisse,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'acompte' THEN p.montant ELSE 0 END) AS total_acomptes,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'solde' THEN p.montant ELSE 0 END) AS total_soldes,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'paiement_unique' THEN p.montant ELSE 0 END) AS total_paiements_uniques,
    SUM(CASE WHEN p.nature = 'remboursement' THEN p.montant ELSE 0 END) AS total_rembourse,
    COUNT(p.paiement_id) AS nb_paiements,
    MAX(p.date_paiement) AS derniere_date_paiement
FROM paiement p
GROUP BY p.commande_id;

CREATE VIEW v_ca_stats AS
SELECT
    cl.ligne_id,
    cl.commande_id,
    c.numero_commande,
    cl.menu_id,
    m.titre AS menu_titre,
    cl.nombre_personne,
    COALESCE(NULLIF(cl.prix_par_personne_snapshot, 0) * cl.nombre_personne, cl.prix_menu + cl.remise_appliquee) AS prix_brut_menu,
    cl.remise_appliquee,
    cl.prix_menu AS prix_net_menu,
    cl.prix_livraison,
    cl.prix_total_ligne,
    cl.taux_tva_snapshot AS taux_tva,
    ROUND(cl.prix_total_ligne / (1 + cl.taux_tva_snapshot / 100), 2) AS prix_total_ligne_ht,
    ROUND(cl.prix_total_ligne - cl.prix_total_ligne / (1 + cl.taux_tva_snapshot / 100), 2) AS tva_ligne,
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
    c.statut,
    c.date_commande,
    c.date_prestation,
    c.ville_livraison,
    c.prix_total AS total_ttc,
    SUM(ROUND(cl.prix_total_ligne / (1 + cl.taux_tva_snapshot / 100), 2)) AS total_ht,
    SUM(ROUND(cl.prix_total_ligne - cl.prix_total_ligne / (1 + cl.taux_tva_snapshot / 100), 2)) AS total_tva,
    COUNT(cl.ligne_id) AS nb_menus,
    SUM(cl.nombre_personne) AS nb_personnes,
    SUM(cl.prix_livraison) AS frais_livraison,
    COALESCE(ch_accept.date_acceptation, c.date_commande) AS date_comptabilisation,
    COALESCE(vpc.total_encaisse, 0.00) AS montant_encaisse,
    ROUND(c.prix_total - COALESCE(vpc.total_encaisse, 0.00), 2) AS solde_restant,
    CASE
        WHEN COALESCE(vpc.total_encaisse, 0.00) <= 0 THEN 'non_paye'
        WHEN ROUND(c.prix_total - COALESCE(vpc.total_encaisse, 0.00), 2) <= 0 THEN 'solde'
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
    c.commande_id, c.numero_commande, c.statut, c.date_commande, c.date_prestation,
    c.ville_livraison, c.prix_total, ch_accept.date_acceptation, vpc.total_encaisse,
    u.prenom, u.nom, u.email;

CREATE VIEW v_ca_mensuel AS
SELECT
    YEAR(date_comptabilisation) AS annee,
    MONTH(date_comptabilisation) AS mois,
    DATE_FORMAT(date_comptabilisation, '%Y-%m') AS annee_mois,
    COUNT(DISTINCT commande_id) AS nb_commandes,
    SUM(total_ttc) AS ca_ttc,
    SUM(total_ht) AS ca_ht,
    SUM(total_tva) AS tva_collectee,
    SUM(nb_personnes) AS nb_personnes,
    ROUND(SUM(total_ttc) / COUNT(DISTINCT commande_id), 2) AS panier_moyen_ttc
FROM v_ca_commandes
GROUP BY YEAR(date_comptabilisation), MONTH(date_comptabilisation), DATE_FORMAT(date_comptabilisation, '%Y-%m');

CREATE VIEW v_ca_par_menu AS
SELECT
    s.menu_id,
    s.menu_titre,
    COUNT(DISTINCT s.commande_id) AS nb_commandes,
    SUM(s.nombre_personne) AS nb_personnes,
    SUM(s.prix_net_menu) AS ca_menu_ttc,
    SUM(ROUND(s.prix_net_menu / (1 + s.taux_tva / 100), 2)) AS ca_menu_ht,
    ROUND(AVG(s.prix_net_menu), 2) AS prix_moyen_menu,
    ROUND(AVG(s.nombre_personne), 1) AS nb_personnes_moyen
FROM v_ca_stats s
GROUP BY s.menu_id, s.menu_titre;
