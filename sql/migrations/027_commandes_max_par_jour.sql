-- Migration 027 : capacité max de commandes par jour (0 = illimité)
INSERT INTO site_config (cle, valeur) VALUES ('commandes_max_par_jour', '0')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
