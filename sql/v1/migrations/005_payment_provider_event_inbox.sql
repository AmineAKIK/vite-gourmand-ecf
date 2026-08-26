-- Tugères V1 — provider-neutral payment event inbox
--
-- Forward-only and resumable:
--   * create the canonical multi-provider inbox if absent;
--   * preserve every legacy Stripe webhook row by copying it;
--   * never delete or rewrite the legacy stripe_webhook_event table.

CREATE TABLE IF NOT EXISTS payment_provider_event (
    provider VARCHAR(30) NOT NULL,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    object_id VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'processing',
    last_error TEXT NULL,
    occurred_at DATETIME NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (provider, event_id),
    KEY idx_payment_provider_event_status (provider, status),
    KEY idx_payment_provider_event_object (provider, object_id, event_type),
    CONSTRAINT chk_payment_provider_event_status
        CHECK (status IN ('processing', 'processed', 'failed', 'ignored'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_provider_event (
    provider,
    event_id,
    event_type,
    object_id,
    status,
    last_error,
    received_at,
    processed_at,
    updated_at
)
SELECT
    'stripe',
    event_id,
    event_type,
    object_id,
    CASE
        WHEN status IN ('processing', 'processed', 'failed') THEN status
        ELSE 'failed'
    END,
    last_error,
    received_at,
    processed_at,
    updated_at
FROM stripe_webhook_event
ON DUPLICATE KEY UPDATE
    event_type = VALUES(event_type),
    object_id = COALESCE(payment_provider_event.object_id, VALUES(object_id)),
    last_error = COALESCE(payment_provider_event.last_error, VALUES(last_error)),
    processed_at = COALESCE(payment_provider_event.processed_at, VALUES(processed_at));
