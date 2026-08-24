<?php

namespace App\Security;

use App\Config\Database;

class RateLimiter
{
    private const DEFAULT_MAX = 5;
    private const DEFAULT_WINDOW = 900;
    private const BLOCK_DURATION = 900;

    /**
     * @throws \RuntimeException when the action is blocked or the limiter cannot be enforced.
     */
    public static function check(
        string $ip,
        string $action,
        int $maxAttempts = self::DEFAULT_MAX,
        int $windowSeconds = self::DEFAULT_WINDOW
    ): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                'SELECT attempts, blocked_until, last_attempt FROM rate_limit WHERE ip = ? AND action = ?'
            );
            $stmt->execute([$ip, $action]);
            $row = $stmt->fetch();

            if (!$row) {
                return;
            }

            if ($row['blocked_until'] && strtotime($row['blocked_until']) > time()) {
                $reste = (int) ceil((strtotime($row['blocked_until']) - time()) / 60);
                throw new \RuntimeException(
                    "Trop de tentatives. Réessayez dans {$reste} minute" . ($reste > 1 ? 's' : '') . '.'
                );
            }

            if (strtotime($row['last_attempt']) < time() - $windowSeconds) {
                self::reset($ip, $action);
                return;
            }

            if ((int) $row['attempts'] >= $maxAttempts) {
                $db->prepare(
                    'UPDATE rate_limit SET blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE ip = ? AND action = ?'
                )->execute([self::BLOCK_DURATION, $ip, $action]);

                $reste = (int) (self::BLOCK_DURATION / 60);
                throw new \RuntimeException(
                    "Trop de tentatives. Votre accès est temporairement bloqué pour {$reste} minutes."
                );
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[security] rate limiter indisponible pour ' . $action . ': ' . $e->getMessage());
            throw new \RuntimeException('Protection anti-abus temporairement indisponible. Réessayez plus tard.');
        }
    }

    public static function record(string $ip, string $action): void
    {
        try {
            Database::getConnection()->prepare(
                'INSERT INTO rate_limit (ip, action, attempts, last_attempt)
                 VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()'
            )->execute([$ip, $action]);
        } catch (\Throwable $e) {
            error_log('[security] impossible d\'enregistrer le rate limit ' . $action . ': ' . $e->getMessage());
        }
    }

    public static function reset(string $ip, string $action): void
    {
        try {
            Database::getConnection()->prepare(
                'DELETE FROM rate_limit WHERE ip = ? AND action = ?'
            )->execute([$ip, $action]);
        } catch (\Throwable $e) {
            error_log('[security] impossible de réinitialiser le rate limit ' . $action . ': ' . $e->getMessage());
        }
    }

    public static function clientIp(): string
    {
        $remote = self::validIp($_SERVER['REMOTE_ADDR'] ?? '') ?? '0.0.0.0';

        if (!self::trustProxyHeaders()) {
            return $remote;
        }

        $cloudflare = self::validIp($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if ($cloudflare !== null) {
            return $cloudflare;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($forwarded) && $forwarded !== '') {
            foreach (explode(',', $forwarded) as $candidate) {
                $ip = self::validIp(trim($candidate));
                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        return $remote;
    }

    private static function trustProxyHeaders(): bool
    {
        $value = getenv('TRUST_PROXY_HEADERS');
        if ($value === false) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL) === true;
    }

    private static function validIp(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }
}
