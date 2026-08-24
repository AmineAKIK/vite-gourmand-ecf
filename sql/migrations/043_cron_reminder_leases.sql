-- Migration 043 — Leases récupérables et observabilité des rappels cron
SET NAMES utf8mb4;

ALTER TABLE cron_rappel_log
    ADD COLUMN lease_token CHAR(32) NULL AFTER date_cible,
    ADD COLUMN lease_until DATETIME NULL AFTER lease_token,
    ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER lease_until,
    ADD COLUMN last_error VARCHAR(500) NULL AFTER attempt_count;

CREATE INDEX idx_cron_rappel_pending
    ON cron_rappel_log (sent_at, lease_until, created_at);
