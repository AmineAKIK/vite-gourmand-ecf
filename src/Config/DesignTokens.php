<?php

namespace App\Config;

use UnexpectedValueException;

final class DesignTokens
{
    /** @return array<string,string> */
    public static function cssVariables(): array
    {
        return self::fromResolver(static fn(string $key): mixed => Configuration::get($key));
    }

    /** @param callable(string):mixed $resolve @return array<string,string> */
    public static function fromResolver(callable $resolve): array
    {
        return [
            '--brand-primary' => self::color($resolve('theme.primary_color'), 'theme.primary_color'),
            '--brand-secondary' => self::color($resolve('theme.secondary_color'), 'theme.secondary_color'),
            '--surface-page' => self::color($resolve('theme.background_color'), 'theme.background_color'),
        ];
    }

    public static function inlineCss(): string
    {
        $declarations = [];
        foreach (self::cssVariables() as $name => $value) {
            $declarations[] = $name . ':' . $value;
        }

        return ':root{' . implode(';', $declarations) . '}';
    }

    private static function color(mixed $value, string $key): string
    {
        if (!is_string($value) || preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1) {
            throw new UnexpectedValueException($key . ' must resolve to a six-digit hexadecimal color.');
        }

        return strtoupper($value);
    }
}
