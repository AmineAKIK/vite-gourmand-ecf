-- Migration 035 — Plan & quotas SaaS
-- Ajoute la clé "plan" dans site_config (valeurs : starter, pro, premium)
-- et "plan_suspendu" (0/1) pour la suspension par AkikSystems.
-- Installe en plan "premium" par défaut les instances existantes (migration non-bloquante).

INSERT INTO site_config (cle, valeur)
VALUES ('plan', 'premium')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);

INSERT INTO site_config (cle, valeur)
VALUES ('plan_suspendu', '0')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
