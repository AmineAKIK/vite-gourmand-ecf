-- Migration 001 : table site_image
-- À exécuter une seule fois sur la base de production

CREATE TABLE IF NOT EXISTS site_image (
    cle VARCHAR(50) PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO site_image (cle, url) VALUES
('hero',        'images/hero-traiteur-bordeaux.webp'),
('preparation', 'images/preparation-traiteur.webp');
