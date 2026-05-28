-- Migration 029 : option de template pour les devis (sobre | premium)
INSERT INTO site_config (cle, valeur) VALUES ('devis_template', 'sobre')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
