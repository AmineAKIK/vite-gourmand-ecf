<?php

namespace Tests\Unit\Models;

use App\Models\FacturationModel;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class FacturationSchemaLifecycleTest extends TestCase
{
    public function testEnsureSchemaIsACompatibilityNoOp(): void
    {
        // The unit test environment has no MySQL connection. Any runtime schema
        // access from this method would therefore fail here.
        FacturationModel::ensureSchema();

        self::addToAssertionCount(1);
    }

    public function testEnsureSchemaContainsNoRuntimeDdl(): void
    {
        $method = new ReflectionMethod(FacturationModel::class, 'ensureSchema');
        $file = (string) file_get_contents((string) $method->getFileName());
        $lines = explode("\n", $file);
        $body = implode("\n", array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        self::assertStringNotContainsString('CREATE TABLE', strtoupper($body));
        self::assertStringNotContainsString('ALTER TABLE', strtoupper($body));
        self::assertStringNotContainsString('Database::getConnection', $body);
    }

    public function testPdfPathIsOwnedByMigration(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 3) . '/sql/migrations/045_facturation_pdf_path.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('ALTER TABLE document_facturation', $migration);
        self::assertStringContainsString('ADD COLUMN pdf_path VARCHAR(255) NULL', $migration);
    }
}
