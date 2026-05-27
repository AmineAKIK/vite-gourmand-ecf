-- 019_legal_pages.sql
-- Ajoute les clés cgv_contenu et mentions_contenu dans site_config.
-- Si vide, les pages /cgv et /mentions affichent le template généré dynamiquement.
INSERT INTO site_config (cle, valeur) VALUES
    ('cgv_contenu',      ''),
    ('mentions_contenu', '')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
