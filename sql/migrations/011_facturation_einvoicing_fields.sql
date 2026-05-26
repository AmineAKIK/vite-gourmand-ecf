-- Migration 011 — Champs de préparation facturation électronique

SET @has_client_siren := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'client_siren'
);
SET @sql := IF(
    @has_client_siren = 0,
    'ALTER TABLE document_facturation ADD COLUMN client_siren VARCHAR(20) NULL AFTER client_code_postal',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_adresse_livraison := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'adresse_livraison'
);
SET @sql := IF(
    @has_adresse_livraison = 0,
    'ALTER TABLE document_facturation ADD COLUMN adresse_livraison VARCHAR(255) NULL AFTER client_siren',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_ville_livraison := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'ville_livraison'
);
SET @sql := IF(
    @has_ville_livraison = 0,
    'ALTER TABLE document_facturation ADD COLUMN ville_livraison VARCHAR(120) NULL AFTER adresse_livraison',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_code_postal_livraison := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'code_postal_livraison'
);
SET @sql := IF(
    @has_code_postal_livraison = 0,
    'ALTER TABLE document_facturation ADD COLUMN code_postal_livraison VARCHAR(20) NULL AFTER ville_livraison',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_categorie_operation := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'categorie_operation'
);
SET @sql := IF(
    @has_categorie_operation = 0,
    'ALTER TABLE document_facturation ADD COLUMN categorie_operation VARCHAR(30) NOT NULL DEFAULT ''mixte'' AFTER code_postal_livraison',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_tva_debits := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_facturation'
      AND COLUMN_NAME = 'option_tva_debits'
);
SET @sql := IF(
    @has_tva_debits = 0,
    'ALTER TABLE document_facturation ADD COLUMN option_tva_debits TINYINT(1) NOT NULL DEFAULT 0 AFTER categorie_operation',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
