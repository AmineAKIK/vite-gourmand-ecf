-- V1 migration 006 — financial cancellation / provider refund reconciliation
-- Forward-only and resumable: no business rows are deleted or rewritten.

SET @has_provider_refund_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_attempt'
      AND COLUMN_NAME = 'provider_refund_id'
);
SET @add_provider_refund_id_sql := IF(
    @has_provider_refund_id = 0,
    'ALTER TABLE payment_attempt ADD COLUMN provider_refund_id VARCHAR(255) DEFAULT NULL AFTER provider_payment_intent_id',
    'SELECT 1'
);
PREPARE add_provider_refund_id_stmt FROM @add_provider_refund_id_sql;
EXECUTE add_provider_refund_id_stmt;
DEALLOCATE PREPARE add_provider_refund_id_stmt;

SET @has_refunded_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_attempt'
      AND COLUMN_NAME = 'refunded_at'
);
SET @add_refunded_at_sql := IF(
    @has_refunded_at = 0,
    'ALTER TABLE payment_attempt ADD COLUMN refunded_at DATETIME DEFAULT NULL AFTER provider_refund_id',
    'SELECT 1'
);
PREPARE add_refunded_at_stmt FROM @add_refunded_at_sql;
EXECUTE add_refunded_at_stmt;
DEALLOCATE PREPARE add_refunded_at_stmt;
