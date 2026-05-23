-- ============================================
-- Seed : 5 menus bordelais de démonstration
-- ============================================

-- PLATS
INSERT INTO plat (titre, categorie_id, allergenes) VALUES
-- Entrées (categorie_id=1)
('Huîtres du Bassin d\'Arcachon',          1, 'Mollusques'),
('Velouté de cèpes',                        1, NULL),
('Foie gras des Landes',                    1, NULL),
('Asperges blanches, sauce mousseline',     1, 'Œufs, Lait'),
('Gaspacho de tomates anciennes et basilic',1, NULL),

-- Plats principaux (categorie_id=2)
('Entrecôte à la bordelaise, frites maison',          2, 'Gluten'),
('Agneau de Pauillac rôti aux herbes',                2, NULL),
('Magret de canard aux cèpes et pommes sarladaises',  2, NULL),
('Tarte fine aux légumes, coulis de tomates',         2, 'Gluten, Œufs'),
('Risotto aux cèpes de Bordeaux, truffe noire',       2, NULL),

-- Desserts (categorie_id=3)
('Cannelés bordelais',                        3, 'Gluten, Œufs, Lait'),
('Millas aux pruneaux',                       3, 'Gluten, Œufs, Lait'),
('Gâteau basque à la crème (sans gluten)',    3, 'Œufs, Lait'),
('Fraisier bordelais',                        3, 'Gluten, Œufs, Lait'),
('Financiers aux amandes et coulis de fruits rouges', 3, 'Fruits à coque');

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

-- ASSOCIATION MENU <-> PLAT
-- Le Grand Bordeaux (menu_id=1) : plats 1, 6, 11
INSERT INTO menu_plat (menu_id, plat_id) VALUES (1,1),(1,6),(1,11);

-- Prestige Médoc (menu_id=2) : plats 2, 7, 12
INSERT INTO menu_plat (menu_id, plat_id) VALUES (2,2),(2,7),(2,12);

-- Fêtes en Gironde (menu_id=3) : plats 3, 8, 13
INSERT INTO menu_plat (menu_id, plat_id) VALUES (3,3),(3,8),(3,13);

-- Printemps Girondin (menu_id=4) : plats 4, 9, 14
INSERT INTO menu_plat (menu_id, plat_id) VALUES (4,4),(4,9),(4,14);

-- Dîner des Chartrons (menu_id=5) : plats 5, 10, 15
INSERT INTO menu_plat (menu_id, plat_id) VALUES (5,5),(5,10),(5,15);
