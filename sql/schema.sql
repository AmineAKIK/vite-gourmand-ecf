-- ============================================
-- VITE & GOURMAND - Base de données relationnelle
-- ============================================
-- Ce fichier contient uniquement le schéma et les données de référence.
-- Pour le compte administrateur initial, voir sql/seed_admin.sql.example
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================
-- TABLES DE RÉFÉRENCE
-- ============================================

CREATE TABLE role (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE regime (
    regime_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE theme (
    theme_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE horaire (
    horaire_id INT AUTO_INCREMENT PRIMARY KEY,
    jour VARCHAR(20) NOT NULL,
    heure_ouverture VARCHAR(10),
    heure_fermeture VARCHAR(10)
);

-- ============================================
-- UTILISATEUR
-- ============================================

CREATE TABLE utilisateur (
    utilisateur_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    adresse VARCHAR(255),
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    pays VARCHAR(100) DEFAULT 'France',
    role_id INT NOT NULL DEFAULT 1,
    actif BOOLEAN DEFAULT TRUE,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES role(role_id)
);

-- ============================================
-- MENU
-- ============================================

CREATE TABLE menu (
    menu_id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(100) NOT NULL,
    description TEXT,
    nombre_personne_minimum INT NOT NULL DEFAULT 2,
    prix_par_personne DOUBLE NOT NULL,
    quantite_restante INT DEFAULT NULL,
    conditions TEXT,
    actif BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    theme_id INT,
    regime_id INT,
    FOREIGN KEY (theme_id) REFERENCES theme(theme_id),
    FOREIGN KEY (regime_id) REFERENCES regime(regime_id)
);

CREATE TABLE menu_image (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    chemin VARCHAR(255) NOT NULL,
    ordre INT DEFAULT 0,
    FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE
);

-- ============================================
-- PLAT
-- ============================================

CREATE TABLE categorie_plat (
    categorie_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL -- 'entree', 'plat', 'dessert'
);

CREATE TABLE plat (
    plat_id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(100) NOT NULL,
    categorie_id INT NOT NULL,
    allergenes TEXT,
    FOREIGN KEY (categorie_id) REFERENCES categorie_plat(categorie_id)
);

-- Relation menu <-> plat (many-to-many)
CREATE TABLE menu_plat (
    menu_id INT NOT NULL,
    plat_id INT NOT NULL,
    PRIMARY KEY (menu_id, plat_id),
    FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
    FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE
);

-- ============================================
-- COMMANDE
-- ============================================

CREATE TABLE commande (
    commande_id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) NOT NULL UNIQUE,
    utilisateur_id INT NOT NULL,
    date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_prestation DATE NOT NULL,
    heure_livraison VARCHAR(10) NOT NULL,
    adresse_livraison VARCHAR(255) NOT NULL,
    ville_livraison VARCHAR(100) NOT NULL,
    code_postal_livraison VARCHAR(10) NOT NULL,
    prix_total DOUBLE NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    -- Statuts : en_attente, accepte, en_preparation, en_cours_livraison, livre, en_attente_materiel, terminee, annulee
    pret_materiel BOOLEAN DEFAULT FALSE,
    motif_annulation TEXT,
    mode_contact_annulation VARCHAR(50),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id)
);

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

CREATE TABLE commande_historique (
    historique_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    ancien_statut VARCHAR(50),
    nouveau_statut VARCHAR(50) NOT NULL,
    commentaire TEXT,
    modifie_par INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    FOREIGN KEY (modifie_par) REFERENCES utilisateur(utilisateur_id)
);

-- ============================================
-- AVIS
-- ============================================

CREATE TABLE avis (
    avis_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL UNIQUE,
    utilisateur_id INT NOT NULL,
    note INT NOT NULL CHECK (note BETWEEN 1 AND 5),
    description VARCHAR(500),
    statut VARCHAR(20) DEFAULT 'en_attente', -- en_attente, valide, refuse
    afficher_accueil BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commande(commande_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id)
);

-- ============================================
-- TOKEN MOT DE PASSE OUBLIÉ
-- ============================================

CREATE TABLE password_reset (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id)
);

-- ============================================
-- IMAGES DU SITE (hero, section accueil)
-- ============================================

CREATE TABLE site_image (
    cle VARCHAR(50) PRIMARY KEY,  -- 'hero', 'preparation'
    url VARCHAR(500) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO site_image (cle, url) VALUES
('hero',        'images/hero-traiteur-bordeaux.webp'),
('preparation', 'images/preparation-traiteur.webp');

CREATE TABLE site_config (
    cle        VARCHAR(80) NOT NULL PRIMARY KEY,
    valeur     VARCHAR(500) NOT NULL DEFAULT '',
    updated_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_config (cle, valeur) VALUES
    ('hero_sous_titre',   'Traiteur bordelais depuis 25 ans'),
    ('hero_paragraphe',   'Depuis 25 ans, Vite & Gourmand accompagne les particuliers et les professionnels avec une cuisine traiteur généreuse, raffinée et préparée à Bordeaux.'),
    ('livraison_base',    '5.00'),
    ('livraison_km',      '0.50'),
    ('reduction_seuil',   '100.00'),
    ('reduction_taux',    '10');

-- ============================================
-- CACHE GÉOCODAGE
-- ============================================

CREATE TABLE geocache (
    ville_key VARCHAR(100) PRIMARY KEY,
    lat DOUBLE NOT NULL,
    lng DOUBLE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INDEX DE PERFORMANCE
-- ============================================

CREATE INDEX idx_commande_statut          ON commande(statut);
CREATE INDEX idx_commande_utilisateur     ON commande(utilisateur_id);
CREATE INDEX idx_commande_date            ON commande(date_prestation);
CREATE INDEX idx_commande_ligne_commande  ON commande_ligne(commande_id);
CREATE INDEX idx_commande_ligne_menu      ON commande_ligne(menu_id);
CREATE INDEX idx_avis_statut           ON avis(statut);
CREATE INDEX idx_avis_utilisateur      ON avis(utilisateur_id);
CREATE INDEX idx_menu_actif            ON menu(actif);
CREATE INDEX idx_menu_image_menu       ON menu_image(menu_id, ordre);
CREATE INDEX idx_historique_commande   ON commande_historique(commande_id);

-- ============================================
-- DONNÉES INITIALES NÉCESSAIRES AU FONCTIONNEMENT
-- ============================================

INSERT INTO role (libelle) VALUES ('utilisateur'), ('employe'), ('administrateur');

INSERT INTO regime (libelle) VALUES ('Classique'), ('Végétarien'), ('Vegan'), ('Sans gluten'), ('Halal');

INSERT INTO theme (libelle) VALUES ('Classique'), ('Noël'), ('Pâques'), ('Événement'), ('Saint-Valentin');

INSERT INTO horaire (jour, heure_ouverture, heure_fermeture) VALUES
('Lundi', '09:00', '18:00'),
('Mardi', '09:00', '18:00'),
('Mercredi', '09:00', '18:00'),
('Jeudi', '09:00', '18:00'),
('Vendredi', '09:00', '20:00'),
('Samedi', '10:00', '20:00'),
('Dimanche', 'Fermé', NULL);

INSERT INTO categorie_plat (libelle) VALUES ('Entrée'), ('Plat principal'), ('Dessert');

-- Pour créer le compte administrateur, voir sql/seed_admin.sql.example
