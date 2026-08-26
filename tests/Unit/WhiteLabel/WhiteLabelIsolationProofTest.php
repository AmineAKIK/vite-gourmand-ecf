<?php

declare(strict_types=1);

use App\Config\ConfigurationResolver;
use App\Config\DesignTokens;
use App\Domain\BrandAsset;
use App\Services\OrderReferenceGenerator;
use PHPUnit\Framework\TestCase;

final class WhiteLabelIsolationProofTest extends TestCase
{
    public function testSameCodeResolvesTwoDistinctTenantIdentitiesAndContentSets(): void
    {
        $tenantA = new ConfigurationResolver([
            'site_nom' => 'Maison Aurore',
            'site_slogan' => 'Cuisine de saison',
            'couleur_principale' => '#112233',
            'couleur_secondaire' => '#445566',
            'couleur_fond' => '#F7F8F9',
            'home_intro_titre' => 'Réceptions sur mesure',
            'home_intro_texte' => 'Une cuisine pensée pour vos événements.',
            'home_cta_libelle' => 'Composer mon événement',
            'home_cta_url' => '/contact',
            'contact_titre' => 'Parlons de votre réception',
            'seo_home_titre' => 'Maison Aurore — Traiteur',
            'footer_texte' => 'Maison Aurore accompagne vos événements.',
            'commande_prefixe' => 'AURORE',
        ]);
        $tenantB = new ConfigurationResolver([
            'site_nom' => 'Atelier Sépia',
            'site_slogan' => 'Tables contemporaines',
            'couleur_principale' => '#7A3E21',
            'couleur_secondaire' => '#C08A57',
            'couleur_fond' => '#FFFDF8',
            'home_intro_titre' => 'Buffets contemporains',
            'home_intro_texte' => 'Des formats souples pour vos équipes et invités.',
            'home_cta_libelle' => 'Demander une proposition',
            'home_cta_url' => '/contact?sujet=devis',
            'contact_titre' => 'Construisons votre menu',
            'seo_home_titre' => 'Atelier Sépia — Réceptions',
            'footer_texte' => 'Atelier Sépia crée des tables contemporaines.',
            'commande_prefixe' => 'SEPIA',
        ]);

        self::assertSame('Maison Aurore', $tenantA->resolve('brand.name'));
        self::assertSame('Atelier Sépia', $tenantB->resolve('brand.name'));
        self::assertNotSame(
            $tenantA->resolve('content.home.intro_title'),
            $tenantB->resolve('content.home.intro_title'),
        );
        self::assertNotSame(
            $tenantA->resolve('content.home.cta_label'),
            $tenantB->resolve('content.home.cta_label'),
        );
        self::assertNotSame($tenantA->resolve('seo.home.title'), $tenantB->resolve('seo.home.title'));
        self::assertNotSame($tenantA->resolve('content.footer.text'), $tenantB->resolve('content.footer.text'));

        $tokensA = DesignTokens::fromResolver($tenantA->resolve(...));
        $tokensB = DesignTokens::fromResolver($tenantB->resolve(...));
        self::assertSame('#112233', $tokensA['--brand-primary']);
        self::assertSame('#7A3E21', $tokensB['--brand-primary']);
        self::assertNotSame($tokensA, $tokensB);

        self::assertSame(
            'AURORE-20260826-1A2B3C4D',
            OrderReferenceGenerator::format((string) $tenantA->resolve('order.number_prefix'), '20260826', '1A2B3C4D'),
        );
        self::assertSame(
            'SEPIA-20260826-1A2B3C4D',
            OrderReferenceGenerator::format((string) $tenantB->resolve('order.number_prefix'), '20260826', '1A2B3C4D'),
        );
    }

    public function testSameAssetContractAcceptsDistinctTenantAssetData(): void
    {
        $assetsA = [
            BrandAsset::LOGO->value => 'https://cdn.example.test/aurore/logo.svg',
            BrandAsset::HERO->value => 'https://cdn.example.test/aurore/hero.webp',
            BrandAsset::FAVICON->value => 'https://cdn.example.test/aurore/favicon.png',
        ];
        $assetsB = [
            BrandAsset::LOGO->value => 'https://cdn.example.test/sepia/logo.svg',
            BrandAsset::HERO->value => 'https://cdn.example.test/sepia/hero.webp',
            BrandAsset::FAVICON->value => 'https://cdn.example.test/sepia/favicon.png',
        ];

        self::assertSame(BrandAsset::storageKeys(), ['logo', 'favicon', 'og_image', 'hero', 'preparation']);
        self::assertNotSame($assetsA[BrandAsset::LOGO->value], $assetsB[BrandAsset::LOGO->value]);
        self::assertNotSame($assetsA[BrandAsset::HERO->value], $assetsB[BrandAsset::HERO->value]);
        self::assertNotSame($assetsA[BrandAsset::FAVICON->value], $assetsB[BrandAsset::FAVICON->value]);
    }
}
