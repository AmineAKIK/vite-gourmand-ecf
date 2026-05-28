CREATE TABLE IF NOT EXISTS notification (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT NOT NULL,
    type            VARCHAR(50)  NOT NULL,
    titre           VARCHAR(255) NOT NULL,
    corps           TEXT         NULL,
    lu              TINYINT(1)   NOT NULL DEFAULT 0,
    commande_id     INT          NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user_lu (utilisateur_id, lu),
    INDEX idx_notif_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
