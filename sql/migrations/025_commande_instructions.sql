-- Sprint 1.4 : instructions / remarques libres sur la commande
ALTER TABLE commande
    ADD COLUMN IF NOT EXISTS instructions TEXT NULL DEFAULT NULL;
