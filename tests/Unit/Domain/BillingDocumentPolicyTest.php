<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\BillingDocumentPolicy;
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
