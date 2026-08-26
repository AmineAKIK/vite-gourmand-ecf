<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

final class PaymentMoneyBoundaryContractTest extends TestCase
{
    public function testLedgerConsumesCanonicalIntegerCentsOnly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Services/PaymentLedgerService.php');
        self::assertIsString($source);

        self::assertStringContainsString('$data[\'montant_cents\']', $source);
        self::assertStringContainsString('canonicalCents', $source);
        self::assertStringNotContainsString('Money::fromDecimal((string) ($data[\'montant_cents\']', $source);
        self::assertStringNotContainsString('Money::fromDecimal((string) $existing[\'montant_cents\']', $source);
        self::assertStringNotContainsString(' AS montant', $source);
    }

    public function testManualPaymentConvertsEuroInputBeforeLedger(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controllers/PaiementController.php');
        self::assertIsString($source);

        self::assertStringContainsString('$paymentData[\'montant_cents\'] = Money::fromDecimal', $source);
        self::assertStringContainsString('unset($paymentData[\'montant\'])', $source);
        self::assertStringContainsString("['total_encaisse_cents']", $source);
        self::assertStringContainsString('Money::toDecimalString($encaisseCents)', $source);
    }

    public function testProviderWebhookPersistsMinorUnitsWithoutDecimalRoundTrip(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Services/PaymentWebhookFulfillmentService.php');
        self::assertIsString($source);

        self::assertStringContainsString('\'montant_cents\' => (int) $validated[\'amount_total\']', $source);
        self::assertStringContainsString('$commandeData[\'prix_total_cents\'] = (int) $validated[\'amount_total\']', $source);
        self::assertStringNotContainsString('Money::toDecimal(', $source);
    }

    public function testStripeRefundUsesStoredCanonicalCentsDirectly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Services/OrderCancellationService.php');
        self::assertIsString($source);

        self::assertStringContainsString('(int) $payment[\'montant_cents\']', $source);
        self::assertStringNotContainsString('$payment[\'montant\']', $source);
        self::assertStringNotContainsString('use App\\Domain\\Money;', $source);
    }

    public function testEmployeePaymentPresentationUsesCentsUntilInputBoundary(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Views/pages/employe/commandes.php');
        self::assertIsString($source);

        self::assertStringContainsString("['total_encaisse_cents']", $source);
        self::assertStringContainsString('formatMoneyCents($prixTotal)', $source);
        self::assertStringContainsString('formatMoneyCents($p[\'montant_cents\'] ?? 0)', $source);
        self::assertStringContainsString('Money::toDecimalString($soldeRestant)', $source);
        self::assertStringContainsString('Number(p.prix || 0) / 100', $source);
    }
}
