<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\BusinessPolicy;
use App\Domain\QuotePolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuotePolicyTest extends TestCase
{
    private function policy(int $validityDays = 14): QuotePolicy
    {
        return new QuotePolicy(new BusinessPolicy(
            static fn(string $key): mixed => $key === 'quote.validity_days' ? $validityDays : null,
        ));
    }

    public function testExpiryUsesConfiguredValidityDays(): void
    {
        self::assertSame(
            '2026-09-07 23:59:59',
            $this->policy(14)->expiryForEmission('2026-08-24')->format('Y-m-d H:i:s'),
        );
    }

    public function testPersistedExpiryWinsOverCurrentConfiguration(): void
    {
        self::assertSame(
            '2026-09-30 23:59:59',
            $this->policy(3)->expiry('2026-09-30 23:59:59', '2026-08-24')->format('Y-m-d H:i:s'),
        );
    }

    public function testQuoteOpenRejectsExpiredQuote(): void
    {
        $this->expectException(RuntimeException::class);
        $this->policy(14)->assertOpen(
            null,
            null,
            '2026-08-24',
            new DateTimeImmutable('2026-09-08 00:00:00'),
        );
    }

    public function testQuoteDecisionIsTerminal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->policy()->assertOpen(
            'accepte',
            null,
            '2026-08-24',
            new DateTimeImmutable('2026-08-25 00:00:00'),
        );
    }

    public function testWorkflowStatesAreCanonical(): void
    {
        $policy = $this->policy(14);
        $base = [
            'type_document' => 'devis',
            'statut' => 'finalise',
            'date_emission' => '2026-08-24',
            'signature_expires_at' => '2026-09-07 23:59:59',
            'statut_devis' => null,
            'sent_at' => null,
        ];
        $now = new DateTimeImmutable('2026-08-27 12:00:00');

        self::assertSame('finalise', $policy->workflowState($base, $now));
        self::assertSame('envoye', $policy->workflowState(array_replace($base, ['sent_at' => '2026-08-26 10:00:00']), $now));
        self::assertSame('accepte', $policy->workflowState(array_replace($base, ['statut_devis' => 'accepte']), $now));
        self::assertSame('refuse', $policy->workflowState(array_replace($base, ['statut_devis' => 'refuse']), $now));
        self::assertSame('expire', $policy->workflowState($base, new DateTimeImmutable('2026-09-08 00:00:00')));
        self::assertSame('brouillon', $policy->workflowState([
            'type_document' => 'devis',
            'statut' => 'brouillon',
        ], $now));
    }

    public function testInvalidEmissionDateFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->policy()->expiryForEmission('invalid');
    }
}
