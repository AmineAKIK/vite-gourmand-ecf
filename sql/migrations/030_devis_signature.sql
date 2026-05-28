-- Migration 030 : signature électronique maison sur les devis
ALTER TABLE document_facturation
    ADD COLUMN token_signature VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN signed_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN signed_ip VARCHAR(45) NULL DEFAULT NULL;
