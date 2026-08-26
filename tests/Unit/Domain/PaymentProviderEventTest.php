<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Payments\PaymentCheckoutSession;
use App\Payments\PaymentProviderEvent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentProviderEventTest extends TestCase
{
    public function testPaidCheckoutRequiresCanonicalCheckoutSession(): void
    {
        $session = new PaymentCheckoutSession(
            provider: 'stripe',
            id: 'cs_test_123',
            status: 'complete',
            paymentStatus: 'paid',
        );

        $event = new PaymentProviderEvent(
            provider: 'stripe',
            id: 'evt_123',
            providerType: 'checkout.session.completed',
            kind: PaymentProviderEvent::CHECKOUT_PAID,
            objectId: 'cs_test_123',
            checkout: $session,
        );

        self::assertSame('stripe', $event->provider);
        self::assertSame(PaymentProviderEvent::CHECKOUT_PAID, $event->kind);
        self::assertSame($session, $event->checkout);
    }

    public function testPaidCheckoutWithoutSessionFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentProviderEvent(
            provider: 'stripe',
            id: 'evt_123',
            providerType: 'checkout.session.completed',
            kind: PaymentProviderEvent::CHECKOUT_PAID,
        );
    }

    public function testUnknownCanonicalKindIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentProviderEvent(
            provider: 'stripe',
            id: 'evt_123',
            providerType: 'unknown.provider.event',
            kind: 'unexpected',
        );
    }

    public function testPaymentFailureCarriesExactAttemptMetadata(): void
    {
        $event = new PaymentProviderEvent(
            provider: 'stripe',
            id: 'evt_failed',
            providerType: 'payment_intent.payment_failed',
            kind: PaymentProviderEvent::PAYMENT_FAILED,
            objectId: 'pi_failed',
            paymentIntentId: 'pi_failed',
            metadata: [
                'draft_id' => '42',
                'attempt_id' => '73',
            ],
        );

        self::assertSame('42', $event->metadata['draft_id']);
        self::assertSame('73', $event->metadata['attempt_id']);
        self::assertSame('pi_failed', $event->paymentIntentId);
    }
}
