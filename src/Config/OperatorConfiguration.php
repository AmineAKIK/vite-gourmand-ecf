<?php

namespace App\Config;

use UnexpectedValueException;

final class OperatorConfiguration
{
    /** @return string|int|float|bool|list<string>|null */
    public static function get(string $key): string|int|float|bool|array|null
    {
        $definition = ConfigurationRegistry::get($key);
        if ($definition->scope !== ConfigurationScope::OPERATOR
            || $definition->source !== ConfigurationSource::ENVIRONMENT
            || $definition->storageKey === null) {
            throw new UnexpectedValueException('Configuration is not operator-owned: ' . $key);
        }

        $raw = Environment::get($definition->storageKey);
        if ($raw === '') {
            if ($definition->hasDefault()) {
                return $definition->defaultValue;
            }
            if ($definition->required) {
                throw new ConfigurationMissingException('Configuration required: ' . $key);
            }

            return null;
        }

        try {
            return $definition->normalize($raw);
        } catch (\InvalidArgumentException $e) {
            throw new ConfigurationInvalidException(
                'Configuration invalid: ' . $key,
                previous: $e,
            );
        }
    }

    public static function isConfigured(string $key): bool
    {
        $definition = ConfigurationRegistry::get($key);
        if ($definition->scope !== ConfigurationScope::OPERATOR
            || $definition->source !== ConfigurationSource::ENVIRONMENT
            || $definition->storageKey === null) {
            throw new UnexpectedValueException('Configuration is not operator-owned: ' . $key);
        }

        return Environment::get($definition->storageKey) !== '';
    }

    /** @return array<string,string> */
    public static function environmentSnapshot(): array
    {
        $environment = [];
        foreach (ConfigurationRegistry::forScope(ConfigurationScope::OPERATOR) as $definition) {
            if ($definition->source !== ConfigurationSource::ENVIRONMENT || $definition->storageKey === null) {
                continue;
            }

            $value = Environment::get($definition->storageKey);
            if ($value !== '') {
                $environment[$definition->storageKey] = $value;
            }
        }

        return $environment;
    }

    public static function string(string $key, string $empty = ''): string
    {
        $value = self::get($key);
        if ($value === null) {
            return $empty;
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException($key . ' must resolve to a string.');
        }

        return $value;
    }
}
