<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\BillingDocumentPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BillingDocumentPolicyTest extends TestCase
{
    public function testDraftIsAccepted(): void
    {
        BillingDocumentPolicy::assertDraft('brouillon');
        self::assertTrue(true);
    }

    public function testFinalizedDocumentCannotBeEditedAsDraft(): void
    {
        $this->expectException(RuntimeException::class);
        BillingDocumentPolicy::assertDraft('finalise');
    }

    public function testQuoteIsOpenBeforeExpiry(): void
    {
        BillingDocumentPolicy::assertQuoteOpen(
            null,
            '2026-09-23 23:59:59',
            '2026-08-24',
            new DateTimeImmutable('2026-09-23 12:00:00'),
        );
        self::assertTrue(true);
    }

    public function testQuoteIsRejectedAfterExpiry(): void
    {
        $this->expectException(RuntimeException::class);
        BillingDocumentPolicy::assertQuoteOpen(
            null,
            '2026-09-23 23:59:59',
            '2026-08-24',
            new DateTimeImmutable('2026-09-24 00:00:00'),
        );
    }

    public function testQuoteDecisionIsTerminal(): void
    {
        $this->expectException(RuntimeException::class);
        BillingDocumentPolicy::assertQuoteOpen(
            'accepte',
            '2026-09-23 23:59:59',
            '2026-08-24',
            new DateTimeImmutable('2026-08-25 00:00:00'),
        );
    }

    public function testLegacyQuoteExpiryFallsBackToEmissionPlusThirtyDays(): void
    {
        self::assertSame(
            '2026-09-23 23:59:59',
            BillingDocumentPolicy::quoteExpiry(null, '2026-08-24')?->format('Y-m-d H:i:s'),
        );
    }

    public function testSignatureExpiryRejectsInvalidEmissionDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BillingDocumentPolicy::signatureExpiry('invalid');
    }

    public function testArchiveStatusValidation(): void
    {
        BillingDocumentPolicy::assertArchiveStatus(null);
        BillingDocumentPolicy::assertArchiveStatus('pending');
        BillingDocumentPolicy::assertArchiveStatus('ready');
        BillingDocumentPolicy::assertArchiveStatus('failed');
        self::assertTrue(true);
    }

    public function testArchiveStatusRejectsUnknownValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BillingDocumentPolicy::assertArchiveStatus('lost');
    }
}
