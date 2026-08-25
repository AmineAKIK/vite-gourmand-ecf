<?php

namespace Tests\Unit\Config;

use App\Config\DesignTokens;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DesignTokensTest extends TestCase
{
    public function testMapsTenantThemeToSemanticTokens(): void
    {
        $tokens = DesignTokens::fromTheme('#123456', '#ABCDEF', '#F4F5F6');

        self::assertSame('#123456', $tokens['--brand-primary']);
        self::assertSame('#ABCDEF', $tokens['--brand-accent']);
        self::assertSame('#F4F5F6', $tokens['--surface-page']);
        self::assertArrayHasKey('--text-primary', $tokens);
        self::assertArrayHasKey('--font-body', $tokens);
        self::assertArrayNotHasKey('--vg-bordeaux', $tokens);
        self::assertArrayNotHasKey('--vg-or', $tokens);
        self::assertArrayNotHasKey('--vg-creme', $tokens);
    }

    public function testRejectsInvalidThemeColor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DesignTokens::fromTheme('red', '#ABCDEF', '#F4F5F6');
    }
}
