-- ============================================
-- Migration 018 : seed neutre (suppression données bordelaises,
--                 insertion menus génériques, config neutre)
-- ============================================
-- Idempotente : INSERT IGNORE + DELETE par titre exact.
-- À exécuter UNE SEULE FOIS sur une nouvelle instance.
-- Sur une instance existante avec de vraies commandes, NE PAS exécuter.
-- ============================================

-- ------------------------------------------
-- 1. Suppression des menus bordelais de démo
-- ------------------------------------------
DELETE FROM menu_plat WHERE menu_id IN (
    SELECT menu_id FROM menu WHERE titre IN (
        'Le Grand Bordeaux', 'Prestige Médoc', 'Fêtes en Gironde',
        'Printemps Girondin', 'Dîner des Chartrons'
    )
);
DELETE FROM menu WHERE titre IN (
    'Le Grand Bordeaux', 'Prestige Médoc', 'Fêtes en Gironde',
    'Printemps Girondin', 'Dîner des Chartrons'
);

-- Suppression des plats bordelais (ceux qui ne sont pas liés à d'autres menus)
DELETE FROM plat_allergene WHERE plat_id IN (
    SELECT plat_id FROM plat WHERE titre IN (
        'Huîtres du Bassin d\'Arcachon',
        'Velouté de cèpes',
        'Foie gras des Landes',
        'Asperges blanches, sauce mousseline',
        'Gaspacho de tomates anciennes et basilic',
        'Entrecôte à la bordelaise, frites maison',
        'Agneau de Pauillac rôti aux herbes',
        'Magret de canard aux cèpes et pommes sarladaises',
        'Tarte fine aux légumes, coulis de tomates',
        'Risotto aux cèpes de Bordeaux, truffe noire',
        'Cannelés bordelais',
        'Millas aux pruneaux',
        'Gâteau basque à la crème (sans gluten)',
        'Fraisier bordelais',
        'Financiers aux amandes et coulis de fruits rouges'
    )
);
DELETE FROM plat WHERE titre IN (
    'Huîtres du Bassin d\'Arcachon',
    'Velouté de cèpes',
    'Foie gras des Landes',
    'Asperges blanches, sauce mousseline',
    'Gaspacho de tomates anciennes et basilic',
    'Entrecôte à la bordelaise, frites maison',
    'Agneau de Pauillac rôti aux herbes',
    'Magret de canard aux cèpes et pommes sarladaises',
    'Tarte fine aux légumes, coulis de tomates',
    'Risotto aux cèpes de Bordeaux, truffe noire',
    'Cannelés bordelais',
    'Millas aux pruneaux',
    'Gâteau basque à la crème (sans gluten)',
    'Fraisier bordelais',
    'Financiers aux amandes et coulis de fruits rouges'
);

-- ------------------------------------------
-- 2. Plats génériques neutres
-- ------------------------------------------
INSERT IGNORE INTO plat (titre, categorie_id) VALUES
-- Entrées (1)
('Velouté de saison',              1),
('Salade composée maison',         1),
('Terrine du chef',                1),
-- Plats (2)
('Viande rôtie aux herbes',        2),
('Poisson du marché en sauce',     2),
('Gratin de légumes de saison',    2),
-- Desserts (3)
('Tarte aux fruits de saison',     3),
('Fondant au chocolat',            3),
('Panna cotta maison',             3);

-- Allergènes plats génériques
-- Velouté de saison : Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 7 FROM plat WHERE titre = 'Velouté de saison';
-- Terrine du chef : Gluten (1)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 1 FROM plat WHERE titre = 'Terrine du chef';
-- Fondant au chocolat : Gluten (1), Œufs (3), Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 1 FROM plat WHERE titre = 'Fondant au chocolat';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 3 FROM plat WHERE titre = 'Fondant au chocolat';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 7 FROM plat WHERE titre = 'Fondant au chocolat';
-- Panna cotta : Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 7 FROM plat WHERE titre = 'Panna cotta maison';
-- Tarte aux fruits : Gluten (1), Œufs (3)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 1 FROM plat WHERE titre = 'Tarte aux fruits de saison';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT plat_id, 3 FROM plat WHERE titre = 'Tarte aux fruits de saison';

-- ------------------------------------------
-- 3. Menus génériques neutres (3 menus)
-- ------------------------------------------
INSERT IGNORE INTO menu (titre, description, nombre_personne_minimum, prix_par_personne, conditions, actif, theme_id, regime_id) VALUES
(
    'Menu Classique',
    'Un menu généreux et convivial pour toutes vos réceptions. Entrée, plat et dessert préparés avec des produits frais de saison, pour régaler vos convives en toute simplicité.',
    6, 35.00,
    'Minimum 6 personnes. Matériel de service inclus.',
    1, 1, 1
),
(
    'Menu Prestige',
    'Notre formule haut de gamme pour vos événements d\'exception. Des produits soigneusement sélectionnés, une présentation soignée et un service irréprochable.',
    10, 55.00,
    'Minimum 10 personnes. Commande à confirmer 7 jours à l\'avance.',
    1, 1, 1
),
(
    'Menu Végétarien',
    'Une sélection raffinée de plats végétariens mettant en valeur les légumes et produits de saison. Idéal pour accueillir tous vos convives quelle que soit leur préférence alimentaire.',
    6, 32.00,
    'Minimum 6 personnes. Disponible selon la saisonnalité des produits.',
    1, 1, 2
);

-- Association menus <-> plats génériques
INSERT IGNORE INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id FROM menu m, plat p
WHERE m.titre = 'Menu Classique'
  AND p.titre IN ('Salade composée maison', 'Viande rôtie aux herbes', 'Tarte aux fruits de saison');

INSERT IGNORE INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id FROM menu m, plat p
WHERE m.titre = 'Menu Prestige'
  AND p.titre IN ('Terrine du chef', 'Poisson du marché en sauce', 'Panna cotta maison');

INSERT IGNORE INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id FROM menu m, plat p
WHERE m.titre = 'Menu Végétarien'
  AND p.titre IN ('Velouté de saison', 'Gratin de légumes de saison', 'Fondant au chocolat');

-- ------------------------------------------
-- 4. Config site neutre
-- ------------------------------------------
INSERT INTO site_config (cle, valeur)
    VALUES ('site_nom', 'Mon Traiteur')
    ON DUPLICATE KEY UPDATE valeur = 'Mon Traiteur';

INSERT INTO site_config (cle, valeur)
    VALUES ('site_slogan', 'Traiteur événementiel')
    ON DUPLICATE KEY UPDATE valeur = 'Traiteur événementiel';

INSERT INTO site_config (cle, valeur)
    VALUES ('site_ville', 'Votre Ville')
    ON DUPLICATE KEY UPDATE valeur = 'Votre Ville';

INSERT INTO site_config (cle, valeur)
    VALUES ('site_code_postal', '')
    ON DUPLICATE KEY UPDATE valeur = '';

INSERT INTO site_config (cle, valeur)
    VALUES ('site_adresse', '')
    ON DUPLICATE KEY UPDATE valeur = '';

INSERT INTO site_config (cle, valeur)
    VALUES ('entreprise_nom', 'Mon Traiteur')
    ON DUPLICATE KEY UPDATE valeur = 'Mon Traiteur';

INSERT INTO site_config (cle, valeur)
    VALUES ('entreprise_ville', 'Votre Ville')
    ON DUPLICATE KEY UPDATE valeur = 'Votre Ville';

INSERT INTO site_config (cle, valeur)
    VALUES ('entreprise_code_postal', '')
    ON DUPLICATE KEY UPDATE valeur = '';

INSERT INTO site_config (cle, valeur)
    VALUES ('entreprise_adresse', '')
    ON DUPLICATE KEY UPDATE valeur = '';
