<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\StripeCheckoutContract;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StripeCheckoutContractTest extends TestCase
{
    public function testCompatibleDraftAndAttemptAreAccepted(): void
    {
        StripeCheckoutContract::assertCompatible([
            'draft_id' => 12,
            'utilisateur_id' => 7,
            'expected_total_cents' => 15990,
            'currency' => 'eur',
        ], [
            'draft_id' => 12,
            'expected_amount_cents' => 15990,
            'currency' => 'EUR',
        ], 7);

        self::assertTrue(true);
    }

    public function testAmountMismatchIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        StripeCheckoutContract::assertCompatible([
            'draft_id' => 12,
            'utilisateur_id' => 7,
            'expected_total_cents' => 15990,
            'currency' => 'eur',
        ], [
            'draft_id' => 12,
            'expected_amount_cents' => 14990,
            'currency' => 'eur',
        ], 7);
    }

    public function testIdempotencyKeyIsStablePerAttemptAndOperation(): void
    {
        self::assertSame(
            'tugeres-attempt-42-checkout-session',
            StripeCheckoutContract::idempotencyKey(42, 'checkout session'),
        );
    }

    public function testDraftMustHaveAtLeastThirtyMinutesRemaining(): void
    {
        $now = 1_700_000_000;
        $this->expectException(RuntimeException::class);

        StripeCheckoutContract::sessionExpiresAt(
            date('Y-m-d H:i:s', $now + 29 * 60),
            $now,
        );
    }

    public function testDraftExpirationIsUsedForStripeSession(): void
    {
        $now = 1_700_000_000;
        $expiresAt = $now + 90 * 60;

        self::assertSame(
            $expiresAt,
            StripeCheckoutContract::sessionExpiresAt(date('Y-m-d H:i:s', $expiresAt), $now),
        );
    }
}
