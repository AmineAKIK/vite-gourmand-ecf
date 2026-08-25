-- Migration 045 — Le stockage PDF de facturation appartient au cycle de migrations
--
-- Les anciennes versions pouvaient créer cette colonne depuis FacturationModel::ensureSchema().
-- Si elle existe déjà sur une base historique, le Migrator tolère uniquement l'erreur MySQL 1060
-- de ce ADD COLUMN simple et enregistre ensuite la migration comme appliquée.
ALTER TABLE document_facturation
    ADD COLUMN pdf_path VARCHAR(255) NULL;
