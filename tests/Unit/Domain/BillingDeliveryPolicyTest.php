<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\BillingDeliveryPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BillingDeliveryPolicyTest extends TestCase
{
    public function testFinalizedDocumentWithEmailCanBeSent(): void
    {
        BillingDeliveryPolicy::assertCanSend([
            'statut' => 'finalise',
            'client_email' => 'client@example.test',
        ]);

        self::assertTrue(true);
    }

    public function testDraftDocumentCannotBeSent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BillingDeliveryPolicy::assertCanSend([
            'statut' => 'brouillon',
            'client_email' => 'client@example.test',
        ]);
    }

    public function testDocumentWithoutEmailCannotBeSent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BillingDeliveryPolicy::assertCanSend([
            'statut' => 'finalise',
            'client_email' => '  ',
        ]);
    }

    public function testFinalizedQuoteWithEmailCanRequestSignature(): void
    {
        BillingDeliveryPolicy::assertCanRequestSignature([
            'type_document' => 'devis',
            'statut' => 'finalise',
            'client_email' => 'client@example.test',
        ]);

        self::assertTrue(true);
    }

    public function testInvoiceCannotRequestSignature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BillingDeliveryPolicy::assertCanRequestSignature([
            'type_document' => 'facture',
            'statut' => 'finalise',
            'client_email' => 'client@example.test',
        ]);
    }
}
