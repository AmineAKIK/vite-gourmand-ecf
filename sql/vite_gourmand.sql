-- ============================================
-- VITE & GOURMAND - Base de données relationnelle
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

CREATE TABLE allergene (
    allergene_id INT AUTO_INCREMENT PRIMARY KEY,
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
    description TEXT,
    photo LONGBLOB,
    photo_chemin VARCHAR(255),
    categorie_id INT NOT NULL,
    FOREIGN KEY (categorie_id) REFERENCES categorie_plat(categorie_id)
);

-- Relation plat <-> allergène (many-to-many)
CREATE TABLE plat_allergene (
    plat_id INT NOT NULL,
    allergene_id INT NOT NULL,
    PRIMARY KEY (plat_id, allergene_id),
    FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE,
    FOREIGN KEY (allergene_id) REFERENCES allergene(allergene_id) ON DELETE CASCADE
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
    menu_id INT NOT NULL,
    date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_prestation DATE NOT NULL,
    heure_livraison VARCHAR(10) NOT NULL,
    adresse_livraison VARCHAR(255) NOT NULL,
    ville_livraison VARCHAR(100) NOT NULL,
    code_postal_livraison VARCHAR(10) NOT NULL,
    nombre_personne INT NOT NULL,
    prix_menu DOUBLE NOT NULL,
    prix_livraison DOUBLE NOT NULL DEFAULT 0,
    prix_total DOUBLE NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    -- Statuts : en_attente, accepte, en_preparation, en_cours_livraison, livre, en_attente_materiel, terminee, annulee
    pret_materiel BOOLEAN DEFAULT FALSE,
    motif_annulation TEXT,
    mode_contact_annulation VARCHAR(50),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id),
    FOREIGN KEY (menu_id) REFERENCES menu(menu_id)
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
-- DONNÉES INITIALES NÉCESSAIRES AU FONCTIONNEMENT
-- ============================================

INSERT INTO role (libelle) VALUES ('utilisateur'), ('employe'), ('administrateur');

INSERT INTO regime (libelle) VALUES ('classique'), ('vegetarien'), ('vegan'), ('sans_gluten'), ('halal');

INSERT INTO theme (libelle) VALUES ('classique'), ('noel'), ('paques'), ('evenement'), ('saint_valentin');

INSERT INTO allergene (libelle) VALUES 
('Gluten'), ('Crustacés'), ('Œufs'), ('Poissons'), ('Arachides'),
('Soja'), ('Lait'), ('Fruits à coque'), ('Céleri'), ('Moutarde'),
('Graines de sésame'), ('Anhydride sulfureux'), ('Lupin'), ('Mollusques');

INSERT INTO horaire (jour, heure_ouverture, heure_fermeture) VALUES
('Lundi', '09:00', '18:00'),
('Mardi', '09:00', '18:00'),
('Mercredi', '09:00', '18:00'),
('Jeudi', '09:00', '18:00'),
('Vendredi', '09:00', '20:00'),
('Samedi', '10:00', '20:00'),
('Dimanche', 'Fermé', NULL);

INSERT INTO categorie_plat (libelle) VALUES ('entree'), ('plat'), ('dessert');

-- Compte administrateur initial demandé pour José.
-- Aucun compte administrateur ne peut être créé depuis l'application.
INSERT INTO utilisateur (email, password, prenom, nom, telephone, adresse, ville, code_postal, role_id) VALUES
('admin@vitegourmand.fr', '$2y$12$Pf9cuDAArTbDZxL8GxcF8ePqdDWjxClzpFHycFuxQVJA3F13Rgnha', 'José', 'Admin', '0600000000', '1 Rue de la Restauration', 'Bordeaux', '33000', 3);
