-- Migration 042 — Journal idempotent des rappels cron
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cron_rappel_log (
    rappel_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    type_rappel VARCHAR(40) NOT NULL,
    date_cible DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uk_cron_rappel_once (commande_id, type_rappel, date_cible),
    KEY idx_cron_rappel_created (created_at),
    CONSTRAINT fk_cron_rappel_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
