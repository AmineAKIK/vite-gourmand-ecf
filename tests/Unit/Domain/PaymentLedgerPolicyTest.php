<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\PaymentLedgerPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentLedgerPolicyTest extends TestCase
{
    public function testCollectionCanReachExactOrderTotal(): void
    {
        PaymentLedgerPolicy::assertCollectionAmount(4_000, 6_000, 10_000);
        self::assertTrue(true);
    }

    public function testCollectionCannotExceedOrderTotal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dépasserait');
        PaymentLedgerPolicy::assertCollectionAmount(4_001, 6_000, 10_000);
    }

    public function testCollectionAmountMustBePositive(): void
    {
        $this->expectException(RuntimeException::class);
        PaymentLedgerPolicy::assertCollectionAmount(0, 0, 10_000);
    }

    public function testUnpaidOrderCanBeEdited(): void
    {
        PaymentLedgerPolicy::assertOrderEditable(0);
        self::assertTrue(true);
    }

    public function testPaidOrderCannotBeEdited(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('déjà encaissée');
        PaymentLedgerPolicy::assertOrderEditable(1);
    }

    public function testManualCancellationRequiresZeroManualBalance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('paiements manuels');
        PaymentLedgerPolicy::assertManualCancellationAllowed(500);
    }

    public function testOperationKeysAreStable(): void
    {
        self::assertSame('payment:42:reversal', PaymentLedgerPolicy::collectionOperationKey(42));
        self::assertSame('stripe-refund:payment:42', PaymentLedgerPolicy::stripeRefundOperationKey(42));
    }
}
