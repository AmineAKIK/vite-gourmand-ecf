-- Sprint 1.1 : support devis et facture d'acompte
-- Colonne pour le montant d'acompte déjà versé (affiché sur la facture de solde)
ALTER TABLE document_facturation
    ADD COLUMN IF NOT EXISTS montant_acompte_verse DECIMAL(10,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS document_acompte_id INT NULL DEFAULT NULL;
