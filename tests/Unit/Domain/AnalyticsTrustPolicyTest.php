<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\AnalyticsTrustPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AnalyticsTrustPolicyTest extends TestCase
{
    public function testPeriodAcceptsEmptyOrIsoDates(): void
    {
        self::assertSame(
            ['date_debut' => '2026-01-02', 'date_fin' => '2026-03-04'],
            AnalyticsTrustPolicy::period('2026-01-02', '2026-03-04'),
        );
        self::assertSame(
            ['date_debut' => '', 'date_fin' => ''],
            AnalyticsTrustPolicy::period('', null),
        );
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnalyticsTrustPolicy::period('2026-02-31', '');
    }

    public function testInvertedPeriodIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnalyticsTrustPolicy::period('2026-04-01', '2026-03-01');
    }

    public function testOptionalPositiveIdIsStrict(): void
    {
        self::assertSame(0, AnalyticsTrustPolicy::optionalPositiveId(''));
        self::assertSame(12, AnalyticsTrustPolicy::optionalPositiveId('12'));

        $this->expectException(InvalidArgumentException::class);
        AnalyticsTrustPolicy::optionalPositiveId('12abc');
    }

    public function testExportFormatIsAllowlisted(): void
    {
        self::assertSame('commandes', AnalyticsTrustPolicy::exportFormat(null));
        self::assertSame('lignes', AnalyticsTrustPolicy::exportFormat('lignes'));

        $this->expectException(InvalidArgumentException::class);
        AnalyticsTrustPolicy::exportFormat('xlsx');
    }

    /** @dataProvider dangerousCsvCells */
    public function testCsvTextNeutralizesSpreadsheetFormulas(string $input): void
    {
        self::assertSame("'" . $input, AnalyticsTrustPolicy::csvText($input));
    }

    public static function dangerousCsvCells(): array
    {
        return [
            ['=HYPERLINK("https://example.test")'],
            ['+SUM(1,1)'],
            ['-2+3'],
            ['@SUM(1,1)'],
            [" \t=1+1"],
        ];
    }

    public function testCsvTextLeavesOrdinaryTextUntouched(): void
    {
        self::assertSame('Client Dupont', AnalyticsTrustPolicy::csvText('Client Dupont'));
        self::assertSame('75000', AnalyticsTrustPolicy::csvText('75000'));
    }
}
