<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\BusinessPolicy;
use App\Domain\DeliveryPolicy;
use App\Services\TermsAndConditionsService;
use PHPUnit\Framework\TestCase;

final class TermsAndConditionsServiceTest extends TestCase
{
    public function testBuildUsesCanonicalBusinessDeliveryAndPaymentPolicies(): void
    {
        $configuration = [
            'order.minimum_lead_hours' => 48,
            'order.maximum_advance_days' => 180,
            'order.cancellation_cutoff_hours' => 72,
            'quote.validity_days' => 21,
            'material.return_days' => 5,
            'material.late_fee_cents' => 1250,
            'business.legal_name' => 'Traiteur Test',
            'business.legal_form' => 'SAS',
            'business.siret' => '12345678901234',
            'business.address.line1' => '1 rue des Tests',
            'business.address.postal_code' => '33000',
            'business.address.city' => 'Bordeaux',
            'business.email' => 'contact@example.test',
            'business.phone' => '0102030405',
            'business.vat_number' => 'FR00123456789',
            'payment.deposit.default_rate_percent' => 30,
            'payment.terms_days' => 15,
            'payment.late_fee_rate_percent' => 12.5,
            'payment.recovery_fee' => '40.00',
            'legal.terms_content' => 'Clause explicitement validée par le traiteur.',
        ];
        $resolve = static fn(string $key): mixed => $configuration[$key] ?? null;

        $paymentPolicies = static fn(): array => [
            [
                'code' => 'wire',
                'label' => 'Virement de test',
                'actif' => true,
                'checkout_enabled' => true,
                'manual_collection_enabled' => true,
                'supports_manual_collection' => true,
                'provider_ready' => true,
                'allow_deposit' => true,
                'allow_balance' => true,
                'allow_single_payment' => true,
                'instructions' => 'Utiliser la référence de commande',
            ],
            [
                'code' => 'provider-card',
                'label' => 'Carte indisponible',
                'actif' => true,
                'checkout_enabled' => true,
                'manual_collection_enabled' => false,
                'supports_manual_collection' => false,
                'provider_ready' => false,
                'allow_deposit' => false,
                'allow_balance' => false,
                'allow_single_payment' => true,
                'instructions' => '',
            ],
        ];

        $service = new TermsAndConditionsService(
            new BusinessPolicy($resolve),
            new DeliveryPolicy(44.84, -0.58, 30, ['33000'], 500, 100),
            $resolve,
            $paymentPolicies,
        );

        $document = $service->build();
        self::assertSame('Traiteur Test — SAS', $document['seller'][0]);
        self::assertContains('SIRET : 12345678901234', $document['seller']);
        self::assertSame('Clause explicitement validée par le traiteur.', $document['explicit_content']);

        $sections = [];
        foreach ($document['sections'] as $section) {
            $sections[$section['title']] = $section;
        }

        self::assertStringContainsString('48 heure(s)', $sections['Commande et devis']['paragraphs'][0]);
        self::assertStringContainsString('180 jour(s)', $sections['Commande et devis']['paragraphs'][0]);
        self::assertStringContainsString('21 jour(s)', $sections['Commande et devis']['paragraphs'][1]);
        self::assertStringContainsString('72 heure(s)', $sections['Annulation et remboursement']['paragraphs'][0]);
        self::assertStringContainsString('remboursement intégral', $sections['Annulation et remboursement']['paragraphs'][0]);
        self::assertStringContainsString('interdit tout remboursement supérieur', $sections['Annulation et remboursement']['paragraphs'][1]);
        self::assertStringContainsString('5.00 € + 1.00 €/km', $sections['Livraison']['paragraphs'][0]);
        self::assertStringContainsString('30 km', $sections['Livraison']['paragraphs'][0]);
        self::assertStringContainsString('5 jour(s)', $sections['Matériel confié']['paragraphs'][0]);
        self::assertStringContainsString('12.50 €', $sections['Matériel confié']['paragraphs'][0]);

        $paymentText = implode(' ', $sections['Moyens de paiement']['items']);
        self::assertStringContainsString('Virement de test', $paymentText);
        self::assertStringContainsString('commande en ligne', $paymentText);
        self::assertStringContainsString('encaissement par l’équipe', $paymentText);
        self::assertStringContainsString('Utiliser la référence de commande', $paymentText);
        self::assertStringNotContainsString('Carte indisponible', $paymentText);

        $paymentTerms = implode(' ', $sections['Conditions de paiement']['items']);
        self::assertStringContainsString('30 %', $paymentTerms);
        self::assertStringContainsString('15 jour(s)', $paymentTerms);
        self::assertStringContainsString('12.5 %', $paymentTerms);
        self::assertStringContainsString('40.00 €', $paymentTerms);
    }

    public function testPresentationHasNoDirectPolicyOrDatabaseLookup(): void
    {
        $root = dirname(__DIR__, 3);
        $view = file_get_contents($root . '/src/Views/pages/cgv.php');
        $controller = file_get_contents($root . '/src/Controllers/PageController.php');
        $service = file_get_contents($root . '/src/Services/TermsAndConditionsService.php');

        self::assertIsString($view);
        self::assertIsString($controller);
        self::assertIsString($service);

        self::assertStringNotContainsString('Configuration::get', $view);
        self::assertStringNotContainsString('PaymentMethodRegistry', $view);
        self::assertStringNotContainsString('db()', $view);
        self::assertStringNotContainsString('Virement bancaire', $view);
        self::assertStringNotContainsString('Carte bancaire', $view);
        self::assertStringNotContainsString('Chèque', $view);

        self::assertStringContainsString('TermsAndConditionsService::fromConfiguration()->build()', $controller);
        self::assertStringContainsString('new BusinessPolicy($resolve)', $service);
        self::assertStringContainsString('DeliveryPolicy::fromConfiguration()', $service);
        self::assertStringContainsString('PaymentMethodRegistry::tenantPolicies()', $service);
    }
}
