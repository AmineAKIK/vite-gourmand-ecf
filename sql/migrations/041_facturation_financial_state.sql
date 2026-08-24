-- Migration 041 — État financier et archivage durable des documents
SET NAMES utf8mb4;

ALTER TABLE document_facturation
    ADD COLUMN IF NOT EXISTS archive_status ENUM('pending','ready','failed') NULL DEFAULT NULL AFTER archive_path,
    ADD COLUMN IF NOT EXISTS archive_last_error VARCHAR(500) NULL DEFAULT NULL AFTER archive_status,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL DEFAULT NULL AFTER archive_last_error,
    ADD COLUMN IF NOT EXISTS source_document_id INT NULL DEFAULT NULL AFTER document_acompte_id,
    ADD COLUMN IF NOT EXISTS signature_token_hash CHAR(64) NULL DEFAULT NULL AFTER token_signature,
    ADD COLUMN IF NOT EXISTS signature_expires_at DATETIME NULL DEFAULT NULL AFTER signature_token_hash,
    ADD UNIQUE KEY uk_document_source_avoir (source_document_id, type_document),
    ADD UNIQUE KEY uk_document_signature_hash (signature_token_hash),
    ADD CONSTRAINT fk_document_source
        FOREIGN KEY (source_document_id) REFERENCES document_facturation(document_id) ON DELETE RESTRICT;

UPDATE document_facturation
SET archive_status = CASE
        WHEN archive_path IS NOT NULL AND archive_path <> '' THEN 'ready'
        WHEN statut = 'finalise' THEN 'pending'
        ELSE NULL
    END,
    archived_at = CASE
        WHEN archive_path IS NOT NULL AND archive_path <> '' THEN COALESCE(finalized_at, updated_at)
        ELSE archived_at
    END
WHERE archive_status IS NULL;

-- Les anciens tokens restent utilisables jusqu'à leur régénération. Les nouveaux liens
-- utilisent uniquement signature_token_hash et une expiration explicite.
