<?php

namespace App\Security;

use App\Config\Database;

class RateLimiter
{
    private const DEFAULT_MAX      = 5;
    private const DEFAULT_WINDOW   = 900;  // 15 minutes
    private const BLOCK_DURATION   = 900;  // 15 minutes de blocage

    /**
     * Vérifie si l'IP est bloquée pour cette action.
     * Lance une exception avec le message à afficher si le seuil est dépassé.
     *
     * @throws \RuntimeException
     */
    public static function check(
        string $ip,
        string $action,
        int $maxAttempts = self::DEFAULT_MAX,
        int $windowSeconds = self::DEFAULT_WINDOW
    ): void {
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare(
                'SELECT attempts, blocked_until, last_attempt FROM rate_limit WHERE ip = ? AND action = ?'
            );
            $stmt->execute([$ip, $action]);
            $row  = $stmt->fetch();

            if (!$row) {
                return;
            }

            // Toujours bloqué ?
            if ($row['blocked_until'] && strtotime($row['blocked_until']) > time()) {
                $reste = ceil((strtotime($row['blocked_until']) - time()) / 60);
                throw new \RuntimeException(
                    "Trop de tentatives. Réessayez dans {$reste} minute" . ($reste > 1 ? 's' : '') . '.'
                );
            }

            // Fenêtre expirée → réinitialiser silencieusement
            if (strtotime($row['last_attempt']) < time() - $windowSeconds) {
                self::reset($ip, $action);
                return;
            }

            if ((int)$row['attempts'] >= $maxAttempts) {
                // Bloquer
                $db->prepare(
                    'UPDATE rate_limit SET blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE ip = ? AND action = ?'
                )->execute([self::BLOCK_DURATION, $ip, $action]);

                $reste = (int)(self::BLOCK_DURATION / 60);
                throw new \RuntimeException(
                    "Trop de tentatives. Votre accès est temporairement bloqué pour {$reste} minutes."
                );
            }
        } catch (\PDOException) {
            // Table absente ou erreur DB → fail open
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            // Fail open
        }
    }

    /**
     * Enregistre une tentative échouée.
     */
    public static function record(string $ip, string $action): void
    {
        try {
            Database::getConnection()->prepare(
                'INSERT INTO rate_limit (ip, action, attempts, last_attempt)
                 VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()'
            )->execute([$ip, $action]);
        } catch (\Throwable) {}
    }

    /**
     * Remet le compteur à zéro (après succès).
     */
    public static function reset(string $ip, string $action): void
    {
        try {
            Database::getConnection()->prepare(
                'DELETE FROM rate_limit WHERE ip = ? AND action = ?'
            )->execute([$ip, $action]);
        } catch (\Throwable) {}
    }

    public static function clientIp(): string
    {
        // Prend l'IP réelle derrière les proxies Railway / Cloudflare
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if ($val) {
                // X-Forwarded-For peut contenir plusieurs IPs séparées par virgule
                $ip = trim(explode(',', $val)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
