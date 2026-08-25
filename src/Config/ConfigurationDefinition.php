<?php

namespace App\Config;

use InvalidArgumentException;

final class ConfigurationDefinition
{
    /**
     * @param array<string,mixed> $constraints
     */
    public function __construct(
        public readonly string $key,
        public readonly ConfigurationScope $scope,
        public readonly ConfigurationType $type,
        public readonly ConfigurationSource $source,
        public readonly ?string $storageKey,
        public readonly bool $required,
        public readonly bool $sensitive,
        public readonly ?string $editableRole,
        public readonly string $group,
        public readonly string $description,
        public readonly mixed $defaultValue = null,
        public readonly array $constraints = [],
        public readonly string $migrationStrategy = 'preserve_storage_key',
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('Configuration key cannot be empty.');
        }

        if ($this->source !== ConfigurationSource::FIXED && ($this->storageKey === null || $this->storageKey === '')) {
            throw new InvalidArgumentException('Non-fixed configuration must declare a storage key.');
        }

        if ($this->source === ConfigurationSource::FIXED && $this->storageKey !== null) {
            throw new InvalidArgumentException('Fixed configuration cannot declare a storage key.');
        }

        if ($this->sensitive && $this->scope !== ConfigurationScope::OPERATOR) {
            throw new InvalidArgumentException('Sensitive configuration must belong to operator scope.');
        }
    }

    public function hasDefault(): bool
    {
        return $this->defaultValue !== null;
    }

    /**
     * @return string|int|float|bool|list<string>|null
     */
    public function normalize(string $raw): string|int|float|bool|array|null
    {
        $candidate = trim($raw);
        if ($candidate === '') {
            if ($this->required) {
                throw new InvalidArgumentException('Configuration required: ' . $this->key);
            }

            return null;
        }

        return match ($this->type) {
            ConfigurationType::STRING,
            ConfigurationType::TEXT => $this->normalizeString($candidate),
            ConfigurationType::EMAIL => $this->normalizeEmail($candidate),
            ConfigurationType::INTEGER => $this->normalizeInteger($candidate),
            ConfigurationType::DECIMAL,
            ConfigurationType::COORDINATE => $this->normalizeDecimal($candidate),
            ConfigurationType::BOOLEAN => $this->normalizeBoolean($candidate),
            ConfigurationType::ENUM => $this->normalizeEnum($candidate),
            ConfigurationType::COLOR => $this->normalizeColor($candidate),
            ConfigurationType::POSTAL_CODE => $this->normalizePostalCode($candidate),
            ConfigurationType::SIRET => $this->normalizeSiret($candidate),
            ConfigurationType::IBAN => $this->normalizeIban($candidate),
            ConfigurationType::BIC => $this->normalizeBic($candidate),
            ConfigurationType::STRING_LIST => $this->normalizeStringList($candidate),
        };
    }

    /** @param string|int|float|bool|list<string>|null $value */
    public function toStorageValue(string|int|float|bool|array|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return implode(',', $value);
        }

        return (string) $value;
    }

    private function normalizeString(string $candidate): string
    {
        $maxLength = $this->constraints['max_length'] ?? null;
        if (is_int($maxLength) && mb_strlen($candidate) > $maxLength) {
            throw new InvalidArgumentException('Configuration too long: ' . $this->key);
        }

        $pattern = $this->constraints['pattern'] ?? null;
        if (is_string($pattern) && preg_match($pattern, $candidate) !== 1) {
            throw new InvalidArgumentException('Configuration format invalid: ' . $this->key);
        }

        return $candidate;
    }

    private function normalizeEmail(string $candidate): string
    {
        if (filter_var($candidate, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Configuration email invalid: ' . $this->key);
        }

        return $this->normalizeString($candidate);
    }

    private function normalizeInteger(string $candidate): int
    {
        $value = filter_var($candidate, FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new InvalidArgumentException('Configuration integer invalid: ' . $this->key);
        }

        $this->assertNumericRange((float) $value);
        return (int) $value;
    }

    private function normalizeDecimal(string $candidate): float
    {
        if (!is_numeric($candidate)) {
            throw new InvalidArgumentException('Configuration decimal invalid: ' . $this->key);
        }

        $value = (float) $candidate;
        $this->assertNumericRange($value);
        return $value;
    }

    private function normalizeBoolean(string $candidate): bool
    {
        return match (strtolower($candidate)) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new InvalidArgumentException('Configuration boolean invalid: ' . $this->key),
        };
    }

    private function normalizeEnum(string $candidate): string
    {
        $values = $this->constraints['values'] ?? [];
        if (!is_array($values) || !in_array($candidate, $values, true)) {
            throw new InvalidArgumentException('Configuration enum invalid: ' . $this->key);
        }

        return $candidate;
    }

    private function normalizeColor(string $candidate): string
    {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $candidate) !== 1) {
            throw new InvalidArgumentException('Configuration color invalid: ' . $this->key);
        }

        return strtoupper($candidate);
    }

    private function normalizePostalCode(string $candidate): string
    {
        if (preg_match('/^\d{5}$/', $candidate) !== 1) {
            throw new InvalidArgumentException('Configuration postal code invalid: ' . $this->key);
        }

        return $candidate;
    }

    private function normalizeSiret(string $candidate): string
    {
        $digits = preg_replace('/\s+/', '', $candidate);
        if (!is_string($digits) || preg_match('/^\d{14}$/', $digits) !== 1) {
            throw new InvalidArgumentException('Configuration SIRET invalid: ' . $this->key);
        }

        return $digits;
    }

    private function normalizeIban(string $candidate): string
    {
        $value = strtoupper((string) preg_replace('/\s+/', '', $candidate));
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value) !== 1) {
            throw new InvalidArgumentException('Configuration IBAN invalid: ' . $this->key);
        }

        return $value;
    }

    private function normalizeBic(string $candidate): string
    {
        $value = strtoupper($candidate);
        if (preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Configuration BIC invalid: ' . $this->key);
        }

        return $value;
    }

    /** @return list<string> */
    private function normalizeStringList(string $candidate): array
    {
        $items = array_values(array_unique(array_filter(array_map('trim', explode(',', $candidate)))));
        $maxItems = $this->constraints['max_items'] ?? null;
        if (is_int($maxItems) && count($items) > $maxItems) {
            throw new InvalidArgumentException('Configuration list too large: ' . $this->key);
        }

        return $items;
    }

    private function assertNumericRange(float $value): void
    {
        $min = $this->constraints['min'] ?? null;
        $max = $this->constraints['max'] ?? null;

        if ((is_int($min) || is_float($min)) && $value < $min) {
            throw new InvalidArgumentException('Configuration below minimum: ' . $this->key);
        }

        if ((is_int($max) || is_float($max)) && $value > $max) {
            throw new InvalidArgumentException('Configuration above maximum: ' . $this->key);
        }
    }
}
