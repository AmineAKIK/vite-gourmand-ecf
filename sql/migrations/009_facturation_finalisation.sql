-- Migration 009 — Finalisation des factures et tickets

SET @has_finalized_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'finalized_at'
);
SET @sql := IF(
    @has_finalized_at = 0,
    'ALTER TABLE document_facturation ADD COLUMN finalized_at DATETIME NULL AFTER updated_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_finalized_by := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'finalized_by'
);
SET @sql := IF(
    @has_finalized_by = 0,
    'ALTER TABLE document_facturation ADD COLUMN finalized_by INT NULL AFTER finalized_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS document_sequence (
    type_document   VARCHAR(20) NOT NULL,
    annee           INT NOT NULL,
    dernier_numero  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (type_document, annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
