-- Migration 010 — Archivage et envoi des documents finalisés

SET @has_archive_path := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'archive_path'
);
SET @sql := IF(
    @has_archive_path = 0,
    'ALTER TABLE document_facturation ADD COLUMN archive_path VARCHAR(255) NULL AFTER finalized_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sent_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'sent_at'
);
SET @sql := IF(
    @has_sent_at = 0,
    'ALTER TABLE document_facturation ADD COLUMN sent_at DATETIME NULL AFTER archive_path',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sent_by := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'sent_by'
);
SET @sql := IF(
    @has_sent_by = 0,
    'ALTER TABLE document_facturation ADD COLUMN sent_by INT NULL AFTER sent_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
