<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function testInitialAndTerminalStatusesAreValid(): void
    {
        self::assertTrue(OrderStatus::isValid(OrderStatus::initial()));
        self::assertTrue(OrderStatus::isValid(OrderStatus::completed()));
        self::assertTrue(OrderStatus::isValid(OrderStatus::cancelled()));
    }

    public function testExpectedForwardTransitionsAreAllowed(): void
    {
        self::assertTrue(OrderStatus::canTransition('en_attente', 'accepte'));
        self::assertTrue(OrderStatus::canTransition('accepte', 'en_preparation'));
        self::assertTrue(OrderStatus::canTransition('en_preparation', 'en_cours_livraison'));
        self::assertTrue(OrderStatus::canTransition('en_cours_livraison', 'livre'));
        self::assertTrue(OrderStatus::canTransition('livre', 'en_attente_materiel'));
        self::assertTrue(OrderStatus::canTransition('livre', 'terminee'));
        self::assertTrue(OrderStatus::canTransition('en_attente_materiel', 'terminee'));
    }

    public function testCancellationIsAllowedOnlyBeforeDeliveredTerminalFlow(): void
    {
        self::assertTrue(OrderStatus::canTransition('en_attente', 'annulee'));
        self::assertTrue(OrderStatus::canTransition('accepte', 'annulee'));
        self::assertTrue(OrderStatus::canTransition('en_preparation', 'annulee'));
        self::assertTrue(OrderStatus::canTransition('en_cours_livraison', 'annulee'));
        self::assertTrue(OrderStatus::canTransition('en_attente_materiel', 'annulee'));

        self::assertFalse(OrderStatus::canTransition('livre', 'annulee'));
        self::assertFalse(OrderStatus::canTransition('terminee', 'annulee'));
    }

    public function testInvalidBackwardAndSkippedTransitionsAreRejected(): void
    {
        self::assertFalse(OrderStatus::canTransition('terminee', 'accepte'));
        self::assertFalse(OrderStatus::canTransition('annulee', 'en_attente'));
        self::assertFalse(OrderStatus::canTransition('en_attente', 'livre'));
        self::assertFalse(OrderStatus::canTransition('accepte', 'en_cours_livraison'));
        self::assertFalse(OrderStatus::canTransition('inconnu', 'accepte'));
        self::assertFalse(OrderStatus::canTransition(null, 'accepte'));
    }

    public function testTerminalStatusesCannotMoveAnywhereElse(): void
    {
        foreach (OrderStatus::all() as $target) {
            if ($target !== 'terminee') {
                self::assertFalse(OrderStatus::canTransition('terminee', $target));
            }
            if ($target !== 'annulee') {
                self::assertFalse(OrderStatus::canTransition('annulee', $target));
            }
        }
    }

    public function testSameStatusIsIdempotentlyAccepted(): void
    {
        self::assertTrue(OrderStatus::canTransition('accepte', 'accepte'));
        self::assertTrue(OrderStatus::canTransition('terminee', 'terminee'));
        self::assertTrue(OrderStatus::canTransition('annulee', 'annulee'));
    }

    public function testRevenueStatusesExcludePendingAndCancelledOrders(): void
    {
        self::assertFalse(OrderStatus::countsTowardRevenue('en_attente'));
        self::assertFalse(OrderStatus::countsTowardRevenue('annulee'));
        self::assertTrue(OrderStatus::countsTowardRevenue('accepte'));
        self::assertTrue(OrderStatus::countsTowardRevenue('terminee'));
    }
}
