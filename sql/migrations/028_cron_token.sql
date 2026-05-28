-- Migration 028 : token secret pour sécuriser les endpoints cron
INSERT INTO site_config (cle, valeur)
VALUES ('cron_secret_token', '')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
