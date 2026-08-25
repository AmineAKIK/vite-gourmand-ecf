<?php

namespace Tests\Unit\Config;

use App\Config\Migrator;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MigratorIdempotenceTest extends TestCase
{
    public function testRejectsDuplicateCreateTableError(): void
    {
        self::assertFalse($this->isAllowed(1050, 'CREATE TABLE example (id INT)'));
    }

    public function testRejectsDuplicateIndexError(): void
    {
        self::assertFalse($this->isAllowed(1061, 'ALTER TABLE example ADD INDEX idx_name (name)'));
    }

    public function testAllowsSingleDuplicateColumnAdd(): void
    {
        self::assertTrue($this->isAllowed(1060, 'ALTER TABLE example ADD COLUMN name VARCHAR(50) NULL'));
    }

    public function testRejectsDuplicateColumnErrorOutsideAlterTable(): void
    {
        self::assertFalse($this->isAllowed(1060, 'CREATE TABLE example (name VARCHAR(50))'));
    }

    public function testAllowsSingleMissingDropTarget(): void
    {
        self::assertTrue($this->isAllowed(1091, 'ALTER TABLE example DROP COLUMN legacy_name'));
        self::assertTrue($this->isAllowed(1091, 'ALTER TABLE example DROP INDEX idx_legacy'));
    }

    public function testRejectsAmbiguousMultipleDropStatement(): void
    {
        self::assertFalse(
            $this->isAllowed(1091, 'ALTER TABLE example DROP COLUMN legacy_name, DROP INDEX idx_legacy'),
        );
    }

    private function isAllowed(int $mysqlCode, string $statement): bool
    {
        $exception = new PDOException('migration failure');
        $exception->errorInfo = ['HY000', $mysqlCode, 'migration failure'];

        $method = new ReflectionMethod(Migrator::class, 'isProvenIdempotentError');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, $exception, $statement);
    }
}
