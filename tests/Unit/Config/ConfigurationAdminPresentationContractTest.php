<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class ConfigurationAdminPresentationContractTest extends TestCase
{
    public function testMissingCommercialValuesArePassedToAdminAsExplicitBlanks(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/Admin/ParametresController.php',
        );

        foreach ([
            'livraison_rayon_max_km',
            'livraison_base',
            'livraison_km',
            'commandes_max_par_jour',
            'reduction_seuil',
            'reduction_taux',
            'regime_tva',
            'acompte_taux_defaut',
            'delai_paiement_jours',
            'penalites_retard_taux',
            'indemnite_recouvrement',
        ] as $storageKey) {
            self::assertStringContainsString("'" . $storageKey . "'", $source);
        }

        self::assertStringContainsString("$config[$storageKey] = ''", $source);
    }

    public function testTenantAdminCannotPersistCronSecret(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/Admin/ParametresController.php',
        );

        self::assertStringContainsString("array_key_exists('cron_secret_token', _POST)", str_replace('$', "\u0001", $source));
        self::assertStringContainsString('CRON_SECRET_TOKEN', $source);
    }

    public function testObsoleteCommercialConstantsAreGone(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Config/config.php');

        self::assertStringNotContainsString("define('LIVRAISON_BASE'", $source);
        self::assertStringNotContainsString("define('LIVRAISON_KM'", $source);
        self::assertStringNotContainsString("define('REDUCTION_TAUX'", $source);
    }
}
