<?php

namespace App\Config;

use App\Models\SiteConfigModel;
use InvalidArgumentException;
use RuntimeException;

final class ConfigurationWriter
{
    public static function write(string $key, string $raw, string $actorRole = 'administrateur'): void
    {
        $definition = ConfigurationRegistry::get($key);
        self::assertWritableBy($definition, $actorRole);

        try {
            $value = $definition->normalize($raw);
        } catch (InvalidArgumentException $e) {
            throw new ConfigurationInvalidException(
                'Configuration invalid: ' . $key,
                previous: $e,
            );
        }

        if ($definition->storageKey === null) {
            throw new RuntimeException('Writable configuration has no storage key: ' . $key);
        }

        SiteConfigModel::set($definition->storageKey, $definition->toStorageValue($value));
        Configuration::reset();
    }

    public static function writeStorageKey(
        string $storageKey,
        string $raw,
        string $actorRole = 'administrateur',
    ): void {
        $definition = ConfigurationRegistry::byStorageKey(ConfigurationSource::SITE_CONFIG, $storageKey);
        self::write($definition->key, $raw, $actorRole);
    }

    private static function assertWritableBy(ConfigurationDefinition $definition, string $actorRole): void
    {
        if ($definition->source !== ConfigurationSource::SITE_CONFIG
            || $definition->scope !== ConfigurationScope::TENANT) {
            throw new RuntimeException('Configuration is not tenant-writable: ' . $definition->key);
        }

        if ($definition->editableRole !== $actorRole) {
            throw new RuntimeException('Configuration cannot be edited by this role: ' . $definition->key);
        }

        if ($definition->sensitive) {
            throw new RuntimeException('Sensitive configuration cannot be stored in site_config: ' . $definition->key);
        }
    }
}
