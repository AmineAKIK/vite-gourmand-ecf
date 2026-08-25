<?php

namespace Tests\Unit\Config;

use App\Config\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private const KEY = 'TUGERES_TEST_PROCESS_ENV';

    protected function tearDown(): void
    {
        putenv(self::KEY);
        unset($_ENV[self::KEY], $_SERVER[self::KEY]);
    }

    public function testReadsProcessEnvironmentWhenEnvSuperglobalIsEmpty(): void
    {
        unset($_ENV[self::KEY], $_SERVER[self::KEY]);
        putenv(self::KEY . '=process-value');

        self::assertSame('process-value', Environment::get(self::KEY, 'fallback'));
    }

    public function testProcessEnvironmentTakesPrecedenceOverSuperglobals(): void
    {
        $_ENV[self::KEY] = 'env-value';
        $_SERVER[self::KEY] = 'server-value';
        putenv(self::KEY . '=process-value');

        self::assertSame('process-value', Environment::get(self::KEY, 'fallback'));
    }

    public function testFallsBackToEnvThenServerThenDefault(): void
    {
        putenv(self::KEY);
        $_ENV[self::KEY] = 'env-value';
        $_SERVER[self::KEY] = 'server-value';
        self::assertSame('env-value', Environment::get(self::KEY, 'fallback'));

        unset($_ENV[self::KEY]);
        self::assertSame('server-value', Environment::get(self::KEY, 'fallback'));

        unset($_SERVER[self::KEY]);
        self::assertSame('fallback', Environment::get(self::KEY, 'fallback'));
    }
}
