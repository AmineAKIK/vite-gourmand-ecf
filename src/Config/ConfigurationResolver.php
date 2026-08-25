<?php

namespace App\Config;

use InvalidArgumentException;

final class ConfigurationResolver
{
    /**
     * @param array<string,string> $siteConfig
     * @param array<string,string> $environment
     */
    public function __construct(
        private readonly array $siteConfig,
        private readonly array $environment = [],
    ) {}

    /** @return string|int|float|bool|list<string>|null */
    public function resolve(string $key): string|int|float|bool|array|null
    {
        $definition = ConfigurationRegistry::get($key);

        if ($definition->source === ConfigurationSource::FIXED) {
            return $definition->defaultValue;
        }

        $raw = $this->rawValue($definition);
        if ($raw === null || trim($raw) === '') {
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
        } catch (InvalidArgumentException $e) {
            throw new ConfigurationInvalidException(
                'Configuration invalid: ' . $key,
                previous: $e,
            );
        }
    }

    public function isExplicitlyConfigured(string $key): bool
    {
        $definition = ConfigurationRegistry::get($key);
        if ($definition->source === ConfigurationSource::FIXED) {
            return true;
        }

        $raw = $this->rawValue($definition);
        return $raw !== null && trim($raw) !== '';
    }

    /** @return list<string> */
    public function missingRequired(?ConfigurationScope $scope = null): array
    {
        $missing = [];

        foreach (ConfigurationRegistry::all() as $definition) {
            if (!$definition->required) {
                continue;
            }

            if ($scope !== null && $definition->scope !== $scope) {
                continue;
            }

            if (!$this->isExplicitlyConfigured($definition->key)) {
                $missing[] = $definition->key;
            }
        }

        sort($missing);
        return $missing;
    }

    private function rawValue(ConfigurationDefinition $definition): ?string
    {
        if ($definition->storageKey === null) {
            return null;
        }

        $source = match ($definition->source) {
            ConfigurationSource::SITE_CONFIG => $this->siteConfig,
            ConfigurationSource::ENVIRONMENT => $this->environment,
            ConfigurationSource::FIXED => [],
        };

        $value = $source[$definition->storageKey] ?? null;
        return is_string($value) ? $value : null;
    }
}
