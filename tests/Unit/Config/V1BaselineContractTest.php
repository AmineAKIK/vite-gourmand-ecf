<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class V1BaselineContractTest extends TestCase
{
    private string $sql;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/sql/v1/001_v1_baseline.sql';
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $this->sql = $contents;
    }

    public function testBaselineIsFreshInstallSchemaNotMigrationHistory(): void
    {
        self::assertStringNotContainsString('ALTER TABLE', $this->sql);
        self::assertStringNotContainsString('IF NOT EXISTS', $this->sql);
        self::assertStringNotContainsString('LegacyAlterTableCompatibility', $this->sql);
        self::assertStringNotContainsString('plat_allergene', $this->sql);
        self::assertStringNotContainsString('allergenes TEXT', $this->sql);
        self::assertStringNotContainsString('CREATE TABLE geocache', $this->sql);
    }

    public function testBaselineContainsCurrentCriticalStructures(): void
    {
        foreach ([
            'CREATE TABLE utilisateur',
            'CREATE TABLE menu',
            'CREATE TABLE plat_allergen',
            'CREATE TABLE commande',
            'CREATE TABLE commande_ligne',
            'CREATE TABLE commande_historique',
            'CREATE TABLE ingredient',
            'CREATE TABLE mouvement_stock',
            'CREATE TABLE document_facturation',
            'CREATE TABLE paiement',
            'CREATE TABLE order_draft',
            'CREATE TABLE payment_attempt',
            'CREATE TABLE stripe_webhook_event',
            'CREATE TABLE payment_refund_attempt',
            'CREATE TABLE order_admission_reservation',
            'CREATE TABLE cron_rappel_log',
            'CREATE VIEW v_paiements_commande',
            'CREATE VIEW v_ca_commandes',
        ] as $requiredFragment) {
            self::assertStringContainsString($requiredFragment, $this->sql, $requiredFragment);
        }
    }

    public function testBaselineDoesNotSeedTenantOrOwnerCommercialPolicy(): void
    {
        foreach ([
            'Vite & Gourmand',
            'Bordeaux',
            'bordelais',
            '25 ans',
            'livraison_base',
            'livraison_km',
            'reduction_seuil',
            'reduction_taux',
            'acompte_taux_defaut',
            'penalites_retard_taux',
            'plan_suspendu',
            "('plan',",
            'license_key',
            'license_hash',
            'hero_sous_titre',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $this->sql, $forbidden);
        }
    }

    public function testBaselineSeedsOnlyStableReferenceContracts(): void
    {
        self::assertStringContainsString("(1, 'utilisateur')", $this->sql);
        self::assertStringContainsString("(2, 'employe')", $this->sql);
        self::assertStringContainsString("(3, 'administrateur')", $this->sql);
        self::assertSame(14, preg_match_all("/\\(\\d+,\\s+'[a-z0-9_]+',\\s+'[^']+',\\s+'[^']*',\\s+\\d+\\)/u", $this->sql));
        self::assertStringNotContainsString('INSERT INTO menu ', $this->sql);
        self::assertStringNotContainsString('INSERT INTO horaire ', $this->sql);
        self::assertStringNotContainsString('INSERT INTO regime ', $this->sql);
        self::assertStringNotContainsString('INSERT INTO theme ', $this->sql);
        self::assertStringNotContainsString('INSERT INTO site_config ', $this->sql);
    }
}
