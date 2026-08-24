<?php

namespace App\Integrations;

final class HttpRetryPolicy
{
    public static function shouldRetry(string $method, int $status, bool $transportFailure): bool
    {
        if (strtoupper($method) !== 'GET') {
            return false;
        }
        if ($transportFailure) {
            return true;
        }

        return $status === 429 || $status >= 500;
    }
}
