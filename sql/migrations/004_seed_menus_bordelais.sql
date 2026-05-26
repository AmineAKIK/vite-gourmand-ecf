-- ============================================
-- Seed : 5 menus bordelais de démonstration
-- ============================================

-- PLATS (sans colonne allergenes — géré via plat_allergene)
INSERT INTO plat (titre, categorie_id) VALUES
-- Entrées (categorie_id=1)
('Huîtres du Bassin d\'Arcachon',          1),
('Velouté de cèpes',                        1),
('Foie gras des Landes',                    1),
('Asperges blanches, sauce mousseline',     1),
('Gaspacho de tomates anciennes et basilic',1),

-- Plats principaux (categorie_id=2)
('Entrecôte à la bordelaise, frites maison',          2),
('Agneau de Pauillac rôti aux herbes',                2),
('Magret de canard aux cèpes et pommes sarladaises',  2),
('Tarte fine aux légumes, coulis de tomates',         2),
('Risotto aux cèpes de Bordeaux, truffe noire',       2),

-- Desserts (categorie_id=3)
('Cannelés bordelais',                        3),
('Millas aux pruneaux',                       3),
('Gâteau basque à la crème (sans gluten)',    3),
('Fraisier bordelais',                        3),
('Financiers aux amandes et coulis de fruits rouges', 3);

-- ALLERGÈNES par plat (via plat_allergene)
-- allergene_id : 1=Gluten, 2=Crustacés, 3=Œufs, 7=Lait, 8=Fruits à coque, 14=Mollusques
-- Plat 1 : Huîtres — Mollusques (14)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 14 FROM plat p WHERE p.titre = 'Huîtres du Bassin d\'Arcachon';
-- Plat 4 : Asperges — Œufs (3), Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 3 FROM plat p WHERE p.titre = 'Asperges blanches, sauce mousseline';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 7 FROM plat p WHERE p.titre = 'Asperges blanches, sauce mousseline';
-- Plat 6 : Entrecôte — Gluten (1)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 1 FROM plat p WHERE p.titre = 'Entrecôte à la bordelaise, frites maison';
-- Plat 9 : Tarte fine — Gluten (1), Œufs (3)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 1 FROM plat p WHERE p.titre = 'Tarte fine aux légumes, coulis de tomates';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 3 FROM plat p WHERE p.titre = 'Tarte fine aux légumes, coulis de tomates';
-- Plat 11 : Cannelés — Gluten (1), Œufs (3), Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 1 FROM plat p WHERE p.titre = 'Cannelés bordelais';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 3 FROM plat p WHERE p.titre = 'Cannelés bordelais';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 7 FROM plat p WHERE p.titre = 'Cannelés bordelais';
-- Plat 12 : Millas — Gluten (1), Œufs (3), Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 1 FROM plat p WHERE p.titre = 'Millas aux pruneaux';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 3 FROM plat p WHERE p.titre = 'Millas aux pruneaux';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 7 FROM plat p WHERE p.titre = 'Millas aux pruneaux';
-- Plat 13 : Gâteau basque — Œufs (3), Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 3 FROM plat p WHERE p.titre = 'Gâteau basque à la crème (sans gluten)';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 7 FROM plat p WHERE p.titre = 'Gâteau basque à la crème (sans gluten)';
-- Plat 14 : Fraisier — Gluten (1), Œufs (3), Lait (7)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 1 FROM plat p WHERE p.titre = 'Fraisier bordelais';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 3 FROM plat p WHERE p.titre = 'Fraisier bordelais';
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 7 FROM plat p WHERE p.titre = 'Fraisier bordelais';
-- Plat 15 : Financiers — Fruits à coque (8)
INSERT IGNORE INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, 8 FROM plat p WHERE p.titre = 'Financiers aux amandes et coulis de fruits rouges';

-- MENUS
INSERT INTO menu (titre, description, nombre_personne_minimum, prix_par_personne, conditions, actif, theme_id, regime_id) VALUES
(
    'Le Grand Bordeaux',
    'Un menu classique qui célèbre les saveurs emblématiques de la région bordelaise. Huîtres d\'Arcachon, entrecôte à la sauce bordelaise et cannelés maison pour un repas généreux et authentique.',
    8, 42.00,
    'Minimum 8 personnes. Matériel de service inclus.',
    1, 1, 1
),
(
    'Prestige Médoc',
    'Un menu d\'exception pour vos grands événements. Agneau de Pauillac élevé sous la mère, accompagné d\'un velouté de cèpes de saison et de millas aux pruneaux, dessert traditionnel girondin.',
    10, 58.00,
    'Minimum 10 personnes. Commande à confirmer 7 jours à l\'avance.',
    1, 4, 5
),
(
    'Fêtes en Gironde',
    'Le menu des fêtes de fin d\'année, entièrement sans gluten. Foie gras des Landes, magret de canard aux cèpes et gâteau basque revisité pour régaler toute la tablée en toute sérénité.',
    6, 65.00,
    'Minimum 6 personnes. Idéal pour les repas de Noël et fêtes de fin d\'année.',
    1, 2, 4
),
(
    'Printemps Girondin',
    'Un menu végétarien de saison qui sublime les légumes du terroir aquitain. Asperges blanches des Landes, tarte fine aux légumes et fraisier bordelais pour accueillir le printemps avec élégance.',
    6, 38.00,
    'Minimum 6 personnes. Disponible selon la saisonnalité des produits.',
    1, 3, 2
),
(
    'Dîner des Chartrons',
    'Un menu vegan raffiné inspiré du quartier des Chartrons, haut lieu de la gastronomie bordelaise. Gaspacho, risotto aux cèpes et truffe, financiers aux amandes — 100 % végétal et 100 % savoureux.',
    4, 45.00,
    'Minimum 4 personnes. Produits locaux et de saison privilégiés.',
    1, 5, 3
);

-- ASSOCIATION MENU <-> PLAT (par titre pour être robuste aux auto_increment)
INSERT INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id
FROM menu m, plat p
WHERE m.titre = 'Le Grand Bordeaux'
  AND p.titre IN ('Huîtres du Bassin d\'Arcachon','Entrecôte à la bordelaise, frites maison','Cannelés bordelais');

INSERT INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id
FROM menu m, plat p
WHERE m.titre = 'Prestige Médoc'
  AND p.titre IN ('Velouté de cèpes','Agneau de Pauillac rôti aux herbes','Millas aux pruneaux');

INSERT INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id
FROM menu m, plat p
WHERE m.titre = 'Fêtes en Gironde'
  AND p.titre IN ('Foie gras des Landes','Magret de canard aux cèpes et pommes sarladaises','Gâteau basque à la crème (sans gluten)');

INSERT INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id
FROM menu m, plat p
WHERE m.titre = 'Printemps Girondin'
  AND p.titre IN ('Asperges blanches, sauce mousseline','Tarte fine aux légumes, coulis de tomates','Fraisier bordelais');

INSERT INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id
FROM menu m, plat p
WHERE m.titre = 'Dîner des Chartrons'
  AND p.titre IN ('Gaspacho de tomates anciennes et basilic','Risotto aux cèpes de Bordeaux, truffe noire','Financiers aux amandes et coulis de fruits rouges');
