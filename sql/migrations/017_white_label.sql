-- Migration 017 — White-label : clés d'identité et de configuration géographique
-- Toutes les valeurs sont des defaults Vite & Gourmand ; l'admin les écrase via /admin/parametres

INSERT INTO site_config (cle, valeur) VALUES
    ('site_nom',                    'Vite & Gourmand'),
    ('site_slogan',                 'Traiteur bordelais'),
    ('site_domaine',                'vitegourmand.fr'),
    ('site_email',                  'contact@vitegourmand.fr'),
    ('site_telephone',              '05 56 00 12 34'),
    ('site_adresse',                '12 rue des Capucins'),
    ('site_code_postal',            '33000'),
    ('site_ville',                  'Bordeaux'),
    ('couleur_principale',          '#8B1A2B'),
    ('couleur_secondaire',          '#D4A843'),
    ('couleur_fond',                '#FDF6EC'),
    ('livraison_lat',               '44.8378'),
    ('livraison_lng',               '-0.5792'),
    ('livraison_codes_postaux_gratuits', '33000,33100,33200,33300,33800')
ON DUPLICATE KEY UPDATE valeur = valeur;
-- ON DUPLICATE KEY UPDATE valeur = valeur => ne remplace pas les valeurs déjà personnalisées
