-- Migration 039 — Intégrité du ledger de stock
-- Rend les écritures automatiques idempotentes et permet les contre-passations sans DELETE.

SET NAMES utf8mb4;

ALTER TABLE mouvement_stock
    ADD COLUMN operation_key VARCHAR(191) NULL AFTER cree_par,
    ADD COLUMN reversal_of_mouvement_id INT NULL AFTER operation_key,
    ADD UNIQUE KEY uk_mouvement_stock_operation (operation_key),
    ADD UNIQUE KEY uk_mouvement_stock_reversal (reversal_of_mouvement_id),
    ADD CONSTRAINT fk_mouvement_stock_reversal
        FOREIGN KEY (reversal_of_mouvement_id) REFERENCES mouvement_stock(mouvement_id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS commande_ingredient_snapshot (
    commande_id    INT NOT NULL,
    ingredient_id  INT NOT NULL,
    quantite       DECIMAL(12,3) NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (commande_id, ingredient_id),
    CONSTRAINT fk_commande_ingredient_snapshot_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    CONSTRAINT fk_commande_ingredient_snapshot_ingredient
        FOREIGN KEY (ingredient_id) REFERENCES ingredient(ingredient_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;