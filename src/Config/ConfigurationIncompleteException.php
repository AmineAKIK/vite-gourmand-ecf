<?php

namespace App\Config;

use RuntimeException;

final class ConfigurationIncompleteException extends RuntimeException
{
    /** @param list<string> $keys */
    public function __construct(
        private readonly array $keys,
        string $context,
    ) {
        parent::__construct(
            'configuration_incomplete:' . $context . ':' . implode(',', $keys),
        );
    }

    /** @return list<string> */
    public function keys(): array
    {
        return $this->keys;
    }
}
