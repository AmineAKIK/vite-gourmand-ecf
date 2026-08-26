<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Payments\PaymentCheckoutSession;
use App\Payments\PaymentReconciliationContract;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentReconciliationContractTest extends TestCase
{
    public function testPaidCheckoutMustMatchProviderDraftAttemptAmountCurrencyAndReferences(): void
    {
        $result = PaymentReconciliationContract::assertPaidCheckout(
            $this->session(),
            $this->draft(),
            $this->attempt(),
        );

        self::assertSame(42, $result['draft_id']);
        self::assertSame(73, $result['attempt_id']);
        self::assertSame(12990, $result['amount_total']);
        self::assertSame('eur', $result['currency']);
        self::assertSame('pi_123', $result['payment_intent']);
    }

    public function testProviderMismatchFailsClosed(): void
    {
        $attempt = $this->attempt();
        $attempt['provider'] = 'other';

        $this->expectException(RuntimeException::class);
        PaymentReconciliationContract::assertPaidCheckout($this->session(), $this->draft(), $attempt);
    }

    public function testPaidCheckoutCannotChangeKnownPaymentIntent(): void
    {
        $attempt = $this->attempt();
        $attempt['provider_payment_intent_id'] = 'pi_other';

        $this->expectException(RuntimeException::class);
        PaymentReconciliationContract::assertPaidCheckout($this->session(), $this->draft(), $attempt);
    }

    private function session(): PaymentCheckoutSession
    {
        return new PaymentCheckoutSession(
            provider: 'stripe',
            id: 'cs_test_123',
            status: 'complete',
            paymentStatus: 'paid',
            amountTotalCents: 12990,
            currency: 'eur',
            paymentIntentId: 'pi_123',
            clientReferenceId: 'CMD-2026-0042',
            metadata: [
                'draft_id' => '42',
                'attempt_id' => '73',
                'numero_commande' => 'CMD-2026-0042',
                'utilisateur_id' => '7',
                'expected_total_cents' => '12990',
                'currency' => 'eur',
            ],
        );
    }

    /** @return array<string,mixed> */
    private function draft(): array
    {
        return [
            'draft_id' => 42,
            'numero_commande' => 'CMD-2026-0042',
            'utilisateur_id' => 7,
            'expected_total_cents' => 12990,
            'currency' => 'eur',
        ];
    }

    /** @return array<string,mixed> */
    private function attempt(): array
    {
        return [
            'attempt_id' => 73,
            'draft_id' => 42,
            'provider' => 'stripe',
            'provider_session_id' => 'cs_test_123',
            'provider_payment_intent_id' => null,
            'expected_amount_cents' => 12990,
            'currency' => 'eur',
        ];
    }
}
