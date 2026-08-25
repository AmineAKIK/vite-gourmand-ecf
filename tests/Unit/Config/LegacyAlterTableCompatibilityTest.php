<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\LegacyAlterTableCompatibility;
use App\Config\SqlStatementSplitter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LegacyAlterTableCompatibilityTest extends TestCase
{
    public function testPaymentMigrationCompoundAlterIsSplitOnlyAtTopLevelCommas(): void
    {
        $actions = $this->legacyAlterActions('040_payment_refund_integrity.sql');

        self::assertCount(6, $actions);
        self::assertStringContainsString("ENUM('encaissement','remboursement')", $actions[0]);
        self::assertStringStartsWith('ADD COLUMN IF NOT EXISTS nature', $actions[0]);
        self::assertStringStartsWith('ADD CONSTRAINT fk_paiement_reversal', $actions[5]);
    }

    public function testBillingMigrationPreservesModifyColumnsAndConstraintOrder(): void
    {
        $actions = $this->legacyAlterActions('041_facturation_financial_state.sql');

        self::assertCount(10, $actions);
        self::assertStringStartsWith('MODIFY COLUMN type_document', $actions[0]);
        self::assertStringStartsWith('ADD COLUMN IF NOT EXISTS archive_status', $actions[1]);
        self::assertStringStartsWith('ADD CONSTRAINT fk_document_source', $actions[9]);
    }

    public function testCompatibilityIsRestrictedToKnownHistoricalMigrations(): void
    {
        $statement = 'ALTER TABLE example ADD COLUMN IF NOT EXISTS legacy_col INT NULL';
        $method = new ReflectionMethod(LegacyAlterTableCompatibility::class, 'supports');
        $method->setAccessible(true);

        self::assertTrue((bool) $method->invoke(null, '040_payment_refund_integrity.sql', $statement));
        self::assertTrue((bool) $method->invoke(null, '041_facturation_financial_state.sql', $statement));
        self::assertFalse((bool) $method->invoke(null, '046_future_change.sql', $statement));
    }

    public function testCompatibilityDoesNotInterceptNormalAlterStatements(): void
    {
        $method = new ReflectionMethod(LegacyAlterTableCompatibility::class, 'supports');
        $method->setAccessible(true);

        self::assertFalse((bool) $method->invoke(
            null,
            '040_payment_refund_integrity.sql',
            'ALTER TABLE paiement ADD COLUMN operation_key VARCHAR(160) NULL',
        ));
    }

    /** @return list<string> */
    private function legacyAlterActions(string $migration): array
    {
        $path = dirname(__DIR__, 3) . '/sql/migrations/' . $migration;
        $sql = file_get_contents($path);
        self::assertNotFalse($sql);

        $alter = null;
        foreach (SqlStatementSplitter::split($sql) as $statement) {
            if (str_starts_with(strtoupper(ltrim($statement)), 'ALTER TABLE ')) {
                $alter = $statement;
                break;
            }
        }
        self::assertNotNull($alter);

        $method = new ReflectionMethod(LegacyAlterTableCompatibility::class, 'parseAlterTable');
        $method->setAccessible(true);
        $parsed = $method->invoke(null, $alter);

        self::assertIsArray($parsed);
        self::assertSame($migration === '040_payment_refund_integrity.sql' ? 'paiement' : 'document_facturation', $parsed[1]);

        return $parsed[2];
    }
}
