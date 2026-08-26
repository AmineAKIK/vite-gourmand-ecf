<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class CanonicalMoneyMigrationContractTest extends TestCase
{
    public function testMigrationReplacesTransactionalDecimalsInsteadOfMirroringThem(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/sql/v1/migrations/002_canonical_money.sql');
        self::assertIsString($sql);

        foreach ([
            'DROP COLUMN prix_total',
            'DROP COLUMN prix_menu',
            'DROP COLUMN prix_livraison',
            'DROP COLUMN prix_total_ligne',
            'DROP COLUMN prix_par_personne_snapshot',
            'DROP COLUMN taux_tva_snapshot',
            'DROP COLUMN taux_reduction_snapshot',
            'DROP COLUMN remise_appliquee',
            'DROP COLUMN montant',
        ] as $requiredDrop) {
            self::assertStringContainsString($requiredDrop, $sql, $requiredDrop);
        }

        foreach ([
            'prix_total_cents',
            'prix_menu_cents',
            'prix_livraison_cents',
            'prix_total_ligne_cents',
            'prix_par_personne_snapshot_cents',
            'taux_tva_menu_basis_points',
            'taux_tva_livraison_basis_points',
            'taux_reduction_basis_points',
            'remise_appliquee_cents',
            'montant_cents',
            'currency',
        ] as $canonicalColumn) {
            self::assertStringContainsString($canonicalColumn, $sql, $canonicalColumn);
        }
    }

    public function testMigrationKeepsMenuAndDeliveryVatSnapshotsSeparate(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/sql/v1/migrations/002_canonical_money.sql');
        self::assertIsString($sql);

        self::assertStringContainsString('taux_tva_menu_basis_points', $sql);
        self::assertStringContainsString('taux_tva_livraison_basis_points', $sql);
        self::assertStringContainsString('taux_tva_menu_id', $sql);
        self::assertStringContainsString('taux_tva_livraison_id', $sql);
        self::assertStringNotContainsString('ADD COLUMN taux_tva_basis_points', $sql);
    }

    public function testProviderFulfillmentPersistsCurrencyPaymentMethodAndBothVatSnapshots(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Services/PaymentWebhookFulfillmentService.php');
        self::assertIsString($source);

        self::assertStringContainsString('prix_total_cents, currency, payment_method_code, instructions', preg_replace('/\s+/', ' ', $source));
        self::assertStringContainsString('taux_tva_menu_basis_points', $source);
        self::assertStringContainsString('taux_tva_livraison_basis_points', $source);
        self::assertStringContainsString('taux_tva_menu_id', $source);
        self::assertStringContainsString('taux_tva_livraison_id', $source);
        self::assertStringNotContainsString('taux_tva_basis_points', $source);
        self::assertStringNotContainsString('taux_tva_id', $source);
    }
}
