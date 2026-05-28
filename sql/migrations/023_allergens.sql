-- Sprint 1.2 : allergènes conformes règlement INCO n°1169/2011 (14 allergènes majeurs)
CREATE TABLE IF NOT EXISTS allergen (
    allergen_id   INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30)  NOT NULL UNIQUE,
    libelle       VARCHAR(80)  NOT NULL,
    emoji         VARCHAR(4)   NOT NULL DEFAULT '',
    ordre         TINYINT      NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO allergen (code, libelle, emoji, ordre) VALUES
    ('gluten',      'Céréales contenant du gluten',         '🌾', 1),
    ('crustaces',   'Crustacés',                            '🦀', 2),
    ('oeufs',       'Œufs',                                 '🥚', 3),
    ('poissons',    'Poissons',                             '🐟', 4),
    ('arachides',   'Arachides',                            '🥜', 5),
    ('soja',        'Soja',                                 '🫘', 6),
    ('lait',        'Lait',                                 '🥛', 7),
    ('fruits_coque','Fruits à coque',                       '🌰', 8),
    ('celeri',      'Céleri',                               '🥬', 9),
    ('moutarde',    'Moutarde',                             '🌿', 10),
    ('sesame',      'Graines de sésame',                    '🌱', 11),
    ('so2',         'Dioxyde de soufre et sulfites',        '🧪', 12),
    ('lupin',       'Lupin',                                '🌸', 13),
    ('mollusques',  'Mollusques',                           '🐚', 14)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), emoji = VALUES(emoji), ordre = VALUES(ordre);

CREATE TABLE IF NOT EXISTS plat_allergen (
    plat_id     INT NOT NULL,
    allergen_id INT NOT NULL,
    PRIMARY KEY (plat_id, allergen_id),
    INDEX idx_plat_allergen_allergen (allergen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
