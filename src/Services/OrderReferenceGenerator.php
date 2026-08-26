<?php

namespace App\Services;

use App\Config\Configuration;
use UnexpectedValueException;

final class OrderReferenceGenerator
{
    public static function generate(): string
    {
        $prefix = Configuration::get('order.number_prefix');
        if (!is_string($prefix) || $prefix === '') {
            throw new UnexpectedValueException('order.number_prefix must resolve to a non-empty string.');
        }

        return self::format($prefix, date('Ymd'), strtoupper(bin2hex(random_bytes(4))));
    }

    public static function format(string $prefix, string $date, string $entropy): string
    {
        $prefix = strtoupper(trim($prefix));
        if (preg_match('/^[A-Z0-9]{1,12}$/', $prefix) !== 1) {
            throw new UnexpectedValueException('Invalid order reference prefix.');
        }
        if (preg_match('/^\d{8}$/', $date) !== 1 || preg_match('/^[A-F0-9]{8}$/', $entropy) !== 1) {
            throw new UnexpectedValueException('Invalid order reference components.');
        }

        return $prefix . '-' . $date . '-' . $entropy;
    }
}
