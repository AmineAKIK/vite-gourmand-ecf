<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class LegacyAlterTableCompatibilityTest extends TestCase
{
    public function testLegacyRuntimeCompatibilityShimHasBeenRemoved(): void
    {
        self::assertFalse(class_exists(\App\Config\LegacyAlterTableCompatibility::class));
        self::assertFileDoesNotExist(dirname(__DIR__, 3) . '/src/Config/LegacyAlterTableCompatibility.php');
    }
}
