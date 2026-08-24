<?php

namespace Tests\Unit\Domain;

use App\Domain\ClientIpPolicy;
use PHPUnit\Framework\TestCase;

final class ClientIpPolicyTest extends TestCase
{
    public function testUntrustedProxyHeadersAreIgnored(): void
    {
        self::assertSame(
            '10.0.0.8',
            ClientIpPolicy::resolve('10.0.0.8', '203.0.113.5', '198.51.100.9', false)
        );
    }

    public function testTrustedCloudflareHeaderHasPriority(): void
    {
        self::assertSame(
            '203.0.113.5',
            ClientIpPolicy::resolve('10.0.0.8', '203.0.113.5', '198.51.100.9', true)
        );
    }

    public function testTrustedForwardedForUsesFirstValidIp(): void
    {
        self::assertSame(
            '198.51.100.9',
            ClientIpPolicy::resolve('10.0.0.8', '', 'garbage, 198.51.100.9, 10.0.0.8', true)
        );
    }

    public function testInvalidForwardedHeadersFallBackToRemoteAddress(): void
    {
        self::assertSame(
            '10.0.0.8',
            ClientIpPolicy::resolve('10.0.0.8', 'not-an-ip', 'also-bad', true)
        );
    }

    public function testInvalidRemoteAddressFallsBackToNeutralAddress(): void
    {
        self::assertSame(
            '0.0.0.0',
            ClientIpPolicy::resolve('invalid', '', '', false)
        );
    }
}
