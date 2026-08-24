-- Migration 038 — Admission atomique des commandes
-- Sérialise les contrôles capacité/quota et réserve une place avant redirection Stripe.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS order_admission_lock (
    scope_key   VARCHAR(64) PRIMARY KEY,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_admission_reservation (
    reservation_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_commande  VARCHAR(50) NOT NULL,
    date_prestation  DATE NOT NULL,
    month_key        CHAR(7) NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'reserved',
    draft_id         BIGINT UNSIGNED DEFAULT NULL,
    commande_id      INT DEFAULT NULL,
    expires_at       DATETIME DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_order_admission_numero (numero_commande),
    UNIQUE KEY uk_order_admission_draft (draft_id),
    UNIQUE KEY uk_order_admission_commande (commande_id),
    KEY idx_order_admission_day (date_prestation, status, expires_at),
    KEY idx_order_admission_month (month_key, status, expires_at),

    CONSTRAINT fk_order_admission_draft
        FOREIGN KEY (draft_id) REFERENCES order_draft(draft_id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_admission_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
