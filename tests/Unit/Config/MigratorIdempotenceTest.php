<?php

namespace Tests\Unit\Config;

use App\Config\Migrator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class MigratorIdempotenceTest extends TestCase
{
    public function testPreReleaseDdlToleranceHasBeenRemoved(): void
    {
        $reflection = new ReflectionClass(Migrator::class);

        self::assertFalse($reflection->hasMethod('isProvenIdempotentError'));
        self::assertFalse($reflection->hasMethod('repairKnownPartialMigration'));
        self::assertFalse($reflection->hasMethod('applyBaseSchemaIfNeeded'));
    }

    public function testForwardMigrationNamesStartAfterBaseline(): void
    {
        $this->validateFiles(['/tmp/002_add_something.sql', '/tmp/003_next_change.sql']);
        self::addToAssertionCount(1);
    }

    public function testRejectsBaselineInsideForwardMigrationDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('baseline 001');

        $this->validateFiles(['/tmp/001_v1_baseline.sql']);
    }

    public function testRejectsDuplicateForwardVersions(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Version de migration dupliquée 002');

        $this->validateFiles(['/tmp/002_first.sql', '/tmp/002_second.sql']);
    }

    public function testRejectsNonCanonicalMigrationFilename(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nom de migration V1 invalide');

        $this->validateFiles(['/tmp/2-bad-name.sql']);
    }

    /** @param list<string> $files */
    private function validateFiles(array $files): void
    {
        $method = new ReflectionMethod(Migrator::class, 'validateFileSet');
        $method->setAccessible(true);
        $method->invoke(null, $files);
    }
}
