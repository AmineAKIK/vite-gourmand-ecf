<?php

namespace Tests\Unit\Integrations;

use App\Integrations\HttpRetryPolicy;
use PHPUnit\Framework\TestCase;

final class HttpRetryPolicyTest extends TestCase
{
    public function testGetRetriesTransportFailuresAndTransientStatuses(): void
    {
        self::assertTrue(HttpRetryPolicy::shouldRetry('GET', 0, true));
        self::assertTrue(HttpRetryPolicy::shouldRetry('GET', 429, false));
        self::assertTrue(HttpRetryPolicy::shouldRetry('GET', 503, false));
    }

    public function testGetDoesNotRetryClientErrorsOrSuccesses(): void
    {
        self::assertFalse(HttpRetryPolicy::shouldRetry('GET', 200, false));
        self::assertFalse(HttpRetryPolicy::shouldRetry('GET', 404, false));
    }

    public function testPostIsNeverRetriedAutomatically(): void
    {
        self::assertFalse(HttpRetryPolicy::shouldRetry('POST', 0, true));
        self::assertFalse(HttpRetryPolicy::shouldRetry('POST', 503, false));
    }
}
