-- Migration 017 — White-label : clés d'identité et de configuration géographique
-- Valeurs neutres par défaut — l'admin les personnalise via /admin/parametres

INSERT INTO site_config (cle, valeur) VALUES
    ('site_nom',                    'Mon Traiteur'),
    ('site_slogan',                 'Traiteur événementiel'),
    ('site_domaine',                ''),
    ('site_email',                  ''),
    ('site_telephone',              ''),
    ('site_adresse',                ''),
    ('site_code_postal',            ''),
    ('site_ville',                  'Votre Ville'),
    ('couleur_principale',          '#8B1A2B'),
    ('couleur_secondaire',          '#D4A843'),
    ('couleur_fond',                '#FDF6EC'),
    ('livraison_lat',               ''),
    ('livraison_lng',               ''),
    ('livraison_codes_postaux_gratuits', '')
ON DUPLICATE KEY UPDATE valeur = valeur;
-- ON DUPLICATE KEY UPDATE valeur = valeur => ne remplace pas les valeurs déjà personnalisées
