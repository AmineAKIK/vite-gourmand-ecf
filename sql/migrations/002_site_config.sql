CREATE TABLE IF NOT EXISTS site_config (
    cle         VARCHAR(80)   NOT NULL PRIMARY KEY,
    valeur      VARCHAR(500)  NOT NULL DEFAULT '',
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_config (cle, valeur) VALUES
    ('hero_sous_titre',  'Traiteur bordelais depuis 25 ans'),
    ('livraison_base',   '5.00'),
    ('livraison_km',     '0.50'),
    ('reduction_seuil',  '100.00'),
    ('reduction_taux',   '10');
