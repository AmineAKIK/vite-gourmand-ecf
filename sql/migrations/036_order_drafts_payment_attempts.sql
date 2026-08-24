-- Migration 036 — Brouillons de commande et tentatives de paiement
-- Persiste l'intention de commande avant redirection vers un PSP afin que
-- le cycle de paiement ne dépende plus uniquement de la session navigateur.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS order_draft (
    draft_id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_commande       VARCHAR(50) NOT NULL,
    utilisateur_id        INT NOT NULL,
    status                VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
    currency              CHAR(3) NOT NULL DEFAULT 'eur',
    expected_total_cents  BIGINT UNSIGNED NOT NULL,
    commande_snapshot     JSON NOT NULL,
    pricing_snapshot      JSON NOT NULL,
    panier_snapshot       JSON NOT NULL,
    commande_id           INT DEFAULT NULL,
    expires_at            DATETIME DEFAULT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_order_draft_numero (numero_commande),
    KEY idx_order_draft_user_status (utilisateur_id, status),
    KEY idx_order_draft_expires (expires_at),
    KEY idx_order_draft_commande (commande_id),

    CONSTRAINT fk_order_draft_user
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_draft_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_attempt (
    attempt_id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    draft_id                   BIGINT UNSIGNED NOT NULL,
    provider                   VARCHAR(30) NOT NULL DEFAULT 'stripe',
    status                     VARCHAR(30) NOT NULL DEFAULT 'created',
    expected_amount_cents      BIGINT UNSIGNED NOT NULL,
    currency                   CHAR(3) NOT NULL DEFAULT 'eur',
    provider_session_id        VARCHAR(255) DEFAULT NULL,
    provider_payment_intent_id VARCHAR(255) DEFAULT NULL,
    last_error                 TEXT DEFAULT NULL,
    created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_payment_attempt_draft (draft_id),
    KEY idx_payment_attempt_status (status),
    UNIQUE KEY uk_payment_attempt_session (provider_session_id),
    UNIQUE KEY uk_payment_attempt_intent (provider_payment_intent_id),

    CONSTRAINT fk_payment_attempt_draft
        FOREIGN KEY (draft_id) REFERENCES order_draft(draft_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
