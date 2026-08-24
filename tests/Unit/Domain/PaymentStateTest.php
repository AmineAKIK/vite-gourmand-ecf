<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\PaymentState;
use PHPUnit\Framework\TestCase;

final class PaymentStateTest extends TestCase
{
    public function testKnownDraftStatusesAreAccepted(): void
    {
        self::assertTrue(PaymentState::isDraftStatus(PaymentState::DRAFT_PENDING));
        self::assertTrue(PaymentState::isDraftStatus(PaymentState::DRAFT_PAID));
        self::assertTrue(PaymentState::isDraftStatus(PaymentState::DRAFT_CANCELLED));
        self::assertTrue(PaymentState::isDraftStatus(PaymentState::DRAFT_FAILED));
        self::assertTrue(PaymentState::isDraftStatus(PaymentState::DRAFT_CONSUMED));
        self::assertFalse(PaymentState::isDraftStatus('unknown'));
    }

    public function testOnlyPendingDraftIsUsableForCheckout(): void
    {
        self::assertTrue(PaymentState::isDraftUsable(PaymentState::DRAFT_PENDING));
        self::assertFalse(PaymentState::isDraftUsable(PaymentState::DRAFT_PAID));
        self::assertFalse(PaymentState::isDraftUsable(PaymentState::DRAFT_CANCELLED));
        self::assertFalse(PaymentState::isDraftUsable(PaymentState::DRAFT_CONSUMED));
    }

    public function testKnownAttemptStatusesAreAccepted(): void
    {
        self::assertTrue(PaymentState::isAttemptStatus(PaymentState::ATTEMPT_CREATED));
        self::assertTrue(PaymentState::isAttemptStatus(PaymentState::ATTEMPT_CHECKOUT_CREATED));
        self::assertTrue(PaymentState::isAttemptStatus(PaymentState::ATTEMPT_PAID));
        self::assertTrue(PaymentState::isAttemptStatus(PaymentState::ATTEMPT_CANCELLED));
        self::assertTrue(PaymentState::isAttemptStatus(PaymentState::ATTEMPT_FAILED));
        self::assertFalse(PaymentState::isAttemptStatus('unknown'));
    }
}
