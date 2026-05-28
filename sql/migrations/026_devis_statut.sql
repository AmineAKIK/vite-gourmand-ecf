-- Migration 026 : statut de devis sur document_facturation
-- Valeurs : NULL (en attente), 'accepte', 'refuse'
ALTER TABLE document_facturation
    ADD COLUMN statut_devis ENUM('accepte','refuse') NULL DEFAULT NULL,
    ADD COLUMN date_decision_devis DATETIME NULL DEFAULT NULL;
