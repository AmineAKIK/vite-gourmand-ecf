<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\StripeWebhookContract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StripeWebhookContractTest extends TestCase
{
    public function testPaidSessionMustMatchDraftAndAttemptExactly(): void
    {
        $result = StripeWebhookContract::assertPaidSession(
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

    #[DataProvider('invalidSessionProvider')]
    public function testRejectsAnyMismatch(array $session, array $draft, array $attempt): void
    {
        $this->expectException(RuntimeException::class);
        StripeWebhookContract::assertPaidSession($session, $draft, $attempt);
    }

    public static function invalidSessionProvider(): iterable
    {
        $session = self::baseSession();
        $draft = self::baseDraft();
        $attempt = self::baseAttempt();

        $unpaid = $session;
        $unpaid['payment_status'] = 'unpaid';
        yield 'unpaid session' => [$unpaid, $draft, $attempt];

        $wrongAmount = $session;
        $wrongAmount['amount_total'] = 12989;
        yield 'wrong amount' => [$wrongAmount, $draft, $attempt];

        $wrongMetadataAmount = $session;
        $wrongMetadataAmount['metadata']['expected_total_cents'] = '12989';
        yield 'wrong metadata amount' => [$wrongMetadataAmount, $draft, $attempt];

        $wrongCurrency = $session;
        $wrongCurrency['currency'] = 'usd';
        yield 'wrong currency' => [$wrongCurrency, $draft, $attempt];

        $wrongMetadataCurrency = $session;
        $wrongMetadataCurrency['metadata']['currency'] = 'usd';
        yield 'wrong metadata currency' => [$wrongMetadataCurrency, $draft, $attempt];

        $wrongSession = $attempt;
        $wrongSession['provider_session_id'] = 'cs_other';
        yield 'wrong provider session' => [$session, $draft, $wrongSession];

        $wrongIntent = $attempt;
        $wrongIntent['provider_payment_intent_id'] = 'pi_other';
        yield 'wrong payment intent' => [$session, $draft, $wrongIntent];

        $wrongDraft = $session;
        $wrongDraft['metadata']['draft_id'] = '43';
        yield 'wrong draft metadata' => [$wrongDraft, $draft, $attempt];

        $wrongUser = $session;
        $wrongUser['metadata']['utilisateur_id'] = '8';
        yield 'wrong user metadata' => [$wrongUser, $draft, $attempt];

        $wrongReference = $session;
        $wrongReference['client_reference_id'] = 'CMD-OTHER';
        yield 'wrong client reference' => [$wrongReference, $draft, $attempt];
    }

    private function session(): array
    {
        return self::baseSession();
    }

    private function draft(): array
    {
        return self::baseDraft();
    }

    private function attempt(): array
    {
        return self::baseAttempt();
    }

    private static function baseSession(): array
    {
        return [
            'id' => 'cs_test_123',
            'payment_status' => 'paid',
            'amount_total' => 12990,
            'currency' => 'eur',
            'client_reference_id' => 'CMD-2026-0042',
            'payment_intent' => 'pi_123',
            'metadata' => [
                'draft_id' => '42',
                'attempt_id' => '73',
                'numero_commande' => 'CMD-2026-0042',
                'utilisateur_id' => '7',
                'expected_total_cents' => '12990',
                'currency' => 'eur',
            ],
        ];
    }

    private static function baseDraft(): array
    {
        return [
            'draft_id' => 42,
            'numero_commande' => 'CMD-2026-0042',
            'utilisateur_id' => 7,
            'expected_total_cents' => 12990,
            'currency' => 'eur',
        ];
    }

    private static function baseAttempt(): array
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
