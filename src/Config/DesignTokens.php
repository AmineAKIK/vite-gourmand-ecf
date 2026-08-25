<?php

namespace App\Config;

use InvalidArgumentException;
use UnexpectedValueException;

final class DesignTokens
{
    /** @return array<string,string> */
    public static function cssVariables(): array
    {
        return self::fromTheme(
            self::color('theme.primary_color'),
            self::color('theme.secondary_color'),
            self::color('theme.background_color'),
        );
    }

    /** @return array<string,string> */
    public static function fromTheme(string $primary, string $accent, string $background): array
    {
        foreach ([$primary, $accent, $background] as $color) {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                throw new InvalidArgumentException('Theme colors must use six-digit hexadecimal notation.');
            }
        }

        return [
            '--brand-primary' => strtoupper($primary),
            '--brand-accent' => strtoupper($accent),
            '--surface-page' => strtoupper($background),
            '--surface-card' => '#FFFFFF',
            '--text-primary' => '#1F2937',
            '--text-muted' => '#6B7280',
            '--border-subtle' => 'rgba(31, 41, 55, .14)',
            '--shadow-card' => '0 8px 24px rgba(31, 41, 55, .08)',
            '--font-body' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            '--font-heading' => 'ui-serif, Georgia, Cambria, "Times New Roman", serif',
        ];
    }

    public static function inlineCss(): string
    {
        $declarations = [];
        foreach (self::cssVariables() as $name => $value) {
            $declarations[] = $name . ': ' . $value . ';';
        }

        return ':root {' . implode(' ', $declarations) . '}';
    }

    private static function color(string $key): string
    {
        $value = Configuration::get($key);
        if (!is_string($value) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            throw new UnexpectedValueException($key . ' must resolve to a six-digit hexadecimal color.');
        }

        return $value;
    }
}
