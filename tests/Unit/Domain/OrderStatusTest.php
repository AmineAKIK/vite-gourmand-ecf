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
        self::assertTrue(OrderStatus::canTransition('livre', 'terminee'));
    }

    public function testInvalidAndBackwardTransitionsAreRejected(): void
    {
        self::assertFalse(OrderStatus::canTransition('terminee', 'accepte'));
        self::assertFalse(OrderStatus::canTransition('annulee', 'en_attente'));
        self::assertFalse(OrderStatus::canTransition('inconnu', 'accepte'));
        self::assertFalse(OrderStatus::canTransition(null, 'accepte'));
    }

    public function testSameStatusIsIdempotentlyAccepted(): void
    {
        self::assertTrue(OrderStatus::canTransition('accepte', 'accepte'));
    }

    public function testRevenueStatusesExcludePendingAndCancelledOrders(): void
    {
        self::assertFalse(OrderStatus::countsTowardRevenue('en_attente'));
        self::assertFalse(OrderStatus::countsTowardRevenue('annulee'));
        self::assertTrue(OrderStatus::countsTowardRevenue('accepte'));
        self::assertTrue(OrderStatus::countsTowardRevenue('terminee'));
    }
}
