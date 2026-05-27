-- 020_license.sql
-- Clés de licence Tugères — liées au domaine du client
INSERT INTO site_config (cle, valeur) VALUES
    ('license_key',    ''),
    ('license_domain', ''),
    ('license_hash',   '')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
