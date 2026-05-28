ALTER TABLE utilisateur
    ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN email_verification_token VARCHAR(64) NULL DEFAULT NULL;

-- Les comptes existants sont considérés vérifiés (pas de rétrospective)
UPDATE utilisateur SET email_verified_at = created_at WHERE email_verified_at IS NULL;
