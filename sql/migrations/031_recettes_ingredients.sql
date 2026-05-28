-- Migration 031 : fiches techniques recettes et ingrédients

-- Ingrédients avec prix unitaire (prix/kg ou prix/unité selon l'unité)
CREATE TABLE IF NOT EXISTS ingredient (
    ingredient_id  INT AUTO_INCREMENT PRIMARY KEY,
    libelle        VARCHAR(100) NOT NULL,
    unite          VARCHAR(20)  NOT NULL DEFAULT 'kg',  -- kg, L, pièce, etc.
    prix_unitaire  DECIMAL(10,4) NOT NULL DEFAULT 0.0000,  -- prix par unité (HT)
    seuil_alerte   DECIMAL(10,3) NULL DEFAULT NULL,         -- stock minimum avant alerte
    actif          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Recette : liste d'ingrédients pour un plat
CREATE TABLE IF NOT EXISTS recette_ligne (
    recette_ligne_id INT AUTO_INCREMENT PRIMARY KEY,
    plat_id          INT          NOT NULL,
    ingredient_id    INT          NOT NULL,
    grammage         DECIMAL(10,3) NOT NULL DEFAULT 0.000,  -- quantité par portion (dans l'unité de l'ingrédient)
    FOREIGN KEY (plat_id)       REFERENCES plat(plat_id)             ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredient(ingredient_id) ON DELETE RESTRICT,
    UNIQUE KEY uq_plat_ingredient (plat_id, ingredient_id)
);

-- Stocks : mouvements entrée/sortie par ingrédient
CREATE TABLE IF NOT EXISTS mouvement_stock (
    mouvement_id   INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id  INT          NOT NULL,
    type_mouvement ENUM('entree','sortie','ajustement') NOT NULL,
    quantite       DECIMAL(10,3) NOT NULL,
    motif          VARCHAR(200) NULL,
    commande_id    INT          NULL,
    cree_par       INT          NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredient(ingredient_id) ON DELETE RESTRICT
);

-- Vue stock courant par ingrédient (calcul depuis les mouvements)
-- Pas de CREATE VIEW ici (pas supporté dans tous les contextes migration) — calculé en PHP
