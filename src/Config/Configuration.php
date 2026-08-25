<?php

namespace App\Config;

use App\Models\SiteConfigModel;

final class Configuration
{
    private static ?ConfigurationResolver $resolver = null;

    /** @return string|int|float|bool|list<string>|null */
    public static function get(string $key): string|int|float|bool|array|null
    {
        return self::resolver()->resolve($key);
    }

    public static function isConfigured(string $key): bool
    {
        return self::resolver()->isExplicitlyConfigured($key);
    }

    /** @return list<string> */
    public static function missingRequired(?ConfigurationScope $scope = null): array
    {
        return self::resolver()->missingRequired($scope);
    }

    public static function reset(): void
    {
        self::$resolver = null;
    }

    private static function resolver(): ConfigurationResolver
    {
        if (self::$resolver instanceof ConfigurationResolver) {
            return self::$resolver;
        }

        return self::$resolver = new ConfigurationResolver(
            SiteConfigModel::getAll(),
            OperatorConfiguration::environmentSnapshot(),
        );
    }
}
