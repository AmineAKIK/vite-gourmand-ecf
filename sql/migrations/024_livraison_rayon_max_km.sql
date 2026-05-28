-- Sprint 1.3 : rayon de livraison configurable (supprime la dépendance Bordeaux)
INSERT INTO site_config (cle, valeur)
VALUES ('livraison_rayon_max_km', '50')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
