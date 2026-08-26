<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Payments\PaymentCheckoutRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentCheckoutRequestTest extends TestCase
{
    public function testCanonicalRequestAcceptsExactGrossDiscountAndExpectedTotal(): void
    {
        $request = new PaymentCheckoutRequest(
            attemptId: 12,
            draftId: 7,
            orderReference: 'CMD-2026-001',
            userId: 5,
            expectedAmountCents: 14500,
            currency: 'EUR',
            expiresAt: time() + 3600,
            successUrl: 'https://example.test/payment/success',
            cancelUrl: 'https://example.test/payment/cancel',
            items: [
                ['name' => 'Menu × 4 pers.', 'amount_cents' => 14000],
                ['name' => 'Livraison', 'amount_cents' => 1500],
            ],
            discountCents: 1000,
        );

        self::assertSame('eur', $request->normalizedCurrency());
        self::assertSame(14500, $request->expectedAmountCents);
    }

    public function testRequestRejectsAmountThatDoesNotMatchItsLines(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentCheckoutRequest(
            attemptId: 12,
            draftId: 7,
            orderReference: 'CMD-2026-001',
            userId: 5,
            expectedAmountCents: 14499,
            currency: 'eur',
            expiresAt: time() + 3600,
            successUrl: 'https://example.test/payment/success',
            cancelUrl: 'https://example.test/payment/cancel',
            items: [['name' => 'Menu', 'amount_cents' => 14500]],
        );
    }

    public function testRequestRejectsExpiredCheckout(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentCheckoutRequest(
            attemptId: 12,
            draftId: 7,
            orderReference: 'CMD-2026-001',
            userId: 5,
            expectedAmountCents: 14500,
            currency: 'eur',
            expiresAt: time() - 1,
            successUrl: 'https://example.test/payment/success',
            cancelUrl: 'https://example.test/payment/cancel',
            items: [['name' => 'Menu', 'amount_cents' => 14500]],
        );
    }
}
