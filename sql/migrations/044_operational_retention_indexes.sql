-- Migration 044 — Indexes pour rétention opérationnelle batchée
SET NAMES utf8mb4;

CREATE INDEX idx_notification_retention
    ON notification (lu, created_at);

CREATE INDEX idx_cron_rappel_retention
    ON cron_rappel_log (sent_at);
