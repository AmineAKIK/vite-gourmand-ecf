<?php

namespace App\Domain;

final class ClientIpPolicy
{
    public static function resolve(
        mixed $remoteAddr,
        mixed $cloudflareIp,
        mixed $forwardedFor,
        bool $trustProxyHeaders
    ): string {
        $remote = self::validIp($remoteAddr) ?? '0.0.0.0';

        if (!$trustProxyHeaders) {
            return $remote;
        }

        $cloudflare = self::validIp($cloudflareIp);
        if ($cloudflare !== null) {
            return $cloudflare;
        }

        if (is_string($forwardedFor) && $forwardedFor !== '') {
            foreach (explode(',', $forwardedFor) as $candidate) {
                $ip = self::validIp(trim($candidate));
                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        return $remote;
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
