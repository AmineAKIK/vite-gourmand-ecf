-- Migration 040 — Intégrité paiements / remboursements
SET NAMES utf8mb4;

ALTER TABLE paiement
    ADD COLUMN IF NOT EXISTS nature ENUM('encaissement','remboursement') NOT NULL DEFAULT 'encaissement' AFTER type_paiement,
    ADD COLUMN IF NOT EXISTS operation_key VARCHAR(160) DEFAULT NULL AFTER note,
    ADD COLUMN IF NOT EXISTS reversal_of_paiement_id INT DEFAULT NULL AFTER operation_key,
    ADD UNIQUE KEY uk_paiement_operation_key (operation_key),
    ADD UNIQUE KEY uk_paiement_reversal (reversal_of_paiement_id),
    ADD CONSTRAINT fk_paiement_reversal
        FOREIGN KEY (reversal_of_paiement_id) REFERENCES paiement(paiement_id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS payment_refund_attempt (
    refund_attempt_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paiement_id INT NOT NULL,
    commande_id INT NOT NULL,
    operation_key VARCHAR(160) NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_payment_reference VARCHAR(191) NOT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    status ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    provider_refund_id VARCHAR(191) DEFAULT NULL,
    last_error VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_refund_operation (operation_key),
    UNIQUE KEY uk_refund_paiement (paiement_id),
    KEY idx_refund_commande (commande_id, status),
    CONSTRAINT fk_refund_paiement FOREIGN KEY (paiement_id) REFERENCES paiement(paiement_id) ON DELETE RESTRICT,
    CONSTRAINT fk_refund_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_cancellation_effect (
    commande_id INT PRIMARY KEY,
    menu_stock_restored_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_cancellation_effect_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_paiements_commande AS
SELECT
    p.commande_id,
    SUM(CASE WHEN p.nature = 'remboursement' THEN -p.montant ELSE p.montant END) AS total_encaisse,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'acompte' THEN p.montant ELSE 0 END) AS total_acomptes,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'solde' THEN p.montant ELSE 0 END) AS total_soldes,
    SUM(CASE WHEN p.nature = 'encaissement' AND p.type_paiement = 'paiement_unique' THEN p.montant ELSE 0 END) AS total_paiements_uniques,
    SUM(CASE WHEN p.nature = 'remboursement' THEN p.montant ELSE 0 END) AS total_rembourse,
    COUNT(p.paiement_id) AS nb_paiements,
    MAX(p.date_paiement) AS derniere_date_paiement
FROM paiement p
GROUP BY p.commande_id;
