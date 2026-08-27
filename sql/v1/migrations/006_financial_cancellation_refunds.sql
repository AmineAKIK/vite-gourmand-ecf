-- V1 migration 006 — financial cancellation / provider refund reconciliation
-- Forward-only and resumable: no business rows are deleted or rewritten.

ALTER TABLE payment_attempt
    ADD COLUMN IF NOT EXISTS provider_refund_id VARCHAR(255) DEFAULT NULL AFTER provider_payment_intent_id,
    ADD COLUMN IF NOT EXISTS refunded_at DATETIME DEFAULT NULL AFTER provider_refund_id;
