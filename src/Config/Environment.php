<?php

namespace App\Config;

final class Environment
{
    public static function get(string $key, string $default = ''): string
    {
        $processValue = getenv($key);
        if ($processValue !== false) {
            return $processValue;
        }

        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }

        return $default;
    }
}
