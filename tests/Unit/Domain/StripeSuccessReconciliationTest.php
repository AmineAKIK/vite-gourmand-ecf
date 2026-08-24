<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\StripeSuccessReconciliation;
use PHPUnit\Framework\TestCase;

final class StripeSuccessReconciliationTest extends TestCase
{
    public function testConfirmedRequiresConsumedDraftCommandeAndPaidAttempt(): void
    {
        self::assertSame(
            StripeSuccessReconciliation::CONFIRMED,
            StripeSuccessReconciliation::state(
                ['status' => 'consumed', 'commande_id' => 12],
                ['status' => 'paid'],
            ),
        );
    }

    public function testPendingKeepsBrowserStateUntilWebhookCommits(): void
    {
        self::assertSame(
            StripeSuccessReconciliation::PENDING,
            StripeSuccessReconciliation::state(
                ['status' => 'pending_payment', 'commande_id' => null],
                ['status' => 'checkout_created'],
            ),
        );
    }

    public function testFailedStateWinsForTerminalFailure(): void
    {
        self::assertSame(
            StripeSuccessReconciliation::FAILED,
            StripeSuccessReconciliation::state(
                ['status' => 'failed', 'commande_id' => null],
                ['status' => 'checkout_created'],
            ),
        );
    }

    public function testPartialPaidStateIsReportedAsInconsistent(): void
    {
        self::assertSame(
            StripeSuccessReconciliation::INCONSISTENT,
            StripeSuccessReconciliation::state(
                ['status' => 'pending_payment', 'commande_id' => null],
                ['status' => 'paid'],
            ),
        );
    }

    public function testCartCanBeClearedWhenItStillMatchesPaidDraft(): void
    {
        $current = [
            ['menu_id' => 5, 'options' => ['boisson' => true, 'pain' => false], 'personnes' => 10],
        ];
        $snapshot = [
            ['personnes' => 10, 'menu_id' => 5, 'options' => ['pain' => false, 'boisson' => true]],
        ];

        self::assertTrue(StripeSuccessReconciliation::shouldClearCart($current, $snapshot));
    }

    public function testChangedCartIsPreserved(): void
    {
        $snapshot = [['menu_id' => 5, 'personnes' => 10]];
        $current = [
            ['menu_id' => 5, 'personnes' => 10],
            ['menu_id' => 8, 'personnes' => 6],
        ];

        self::assertFalse(StripeSuccessReconciliation::shouldClearCart($current, $snapshot));
    }
}
