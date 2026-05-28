CREATE TABLE IF NOT EXISTS rate_limit (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ip          VARCHAR(45)  NOT NULL,
    action      VARCHAR(50)  NOT NULL,
    attempts    INT          NOT NULL DEFAULT 1,
    last_attempt DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blocked_until DATETIME   NULL DEFAULT NULL,
    UNIQUE KEY uq_ip_action (ip, action)
);
