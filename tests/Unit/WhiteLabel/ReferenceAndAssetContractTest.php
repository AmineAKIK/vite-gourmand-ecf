<?php

use App\Config\ConfigurationCompleteness;
use App\Config\ConfigurationRegistry;
use App\Domain\BrandAsset;
use App\Services\OrderReferenceGenerator;
use PHPUnit\Framework\TestCase;

final class ReferenceAndAssetContractTest extends TestCase
{
    public function testOrderReferencePrefixIsTenantOwnedAndOrderingCritical(): void
    {
        $definition = ConfigurationRegistry::get('order.number_prefix');
        self::assertTrue($definition->required);
        self::assertSame('commande_prefixe', $definition->storageKey);
        self::assertContains('order.number_prefix', ConfigurationCompleteness::keys('ordering'));
        self::assertContains('order.number_prefix', ConfigurationCompleteness::keys('checkout'));
        self::assertSame('ACME-20260826-1A2B3C4D', OrderReferenceGenerator::format('acme', '20260826', '1A2B3C4D'));
    }

    public function testBrandAssetVocabularyIsClosedAndSemantic(): void
    {
        self::assertSame(['logo', 'favicon', 'og_image', 'hero', 'preparation'], BrandAsset::storageKeys());
    }

    public function testHistoricalOrderBrandPrefixAndFreeAssetReadsAreAbsent(): void
    {
        $root = dirname(__DIR__, 3);
        $runtime = '';
        foreach (['src', 'public'] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
            foreach ($it as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
                    continue;
                }
                $runtime .= file_get_contents($file->getPathname()) ?: '';
            }
        }
        self::assertStringNotContainsString("'VG-'", $runtime);
        self::assertStringNotContainsString('generateNumeroCommande', $runtime);
        self::assertStringNotContainsString("SiteImageModel::get('", $runtime);
        self::assertStringNotContainsString("SiteImageModel::set('", $runtime);
    }
}
