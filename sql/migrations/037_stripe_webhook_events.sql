-- Migration 037 — Idempotence durable des webhooks Stripe
-- Chaque livraison Stripe est tracée par event_id. Le traitement métier reste
-- atomique : l'événement n'est marqué traité que si la commande, le paiement,
-- le stock et les états draft/attempt ont été validés dans la même transaction.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS stripe_webhook_event (
    event_id       VARCHAR(255) PRIMARY KEY,
    event_type     VARCHAR(100) NOT NULL,
    object_id      VARCHAR(255) DEFAULT NULL,
    status         VARCHAR(30) NOT NULL DEFAULT 'processing',
    last_error     TEXT DEFAULT NULL,
    received_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at   DATETIME DEFAULT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_stripe_webhook_event_status (status),
    KEY idx_stripe_webhook_event_object (object_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE order_draft
    DROP INDEX idx_order_draft_commande,
    ADD UNIQUE KEY uk_order_draft_commande (commande_id);
