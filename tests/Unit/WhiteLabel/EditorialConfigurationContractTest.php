<?php

use App\Config\ConfigurationRegistry;
use PHPUnit\Framework\TestCase;

final class EditorialConfigurationContractTest extends TestCase
{
    public function testEditorialSurfaceIsCanonicalTenantConfiguration(): void
    {
        $keys = [
            'content.home.hero_subtitle', 'content.home.hero_paragraph', 'content.home.intro_title',
            'content.home.intro_body', 'content.home.cta_label', 'content.home.cta_url',
            'content.home.reviews_title', 'content.home.reviews_description', 'content.contact.title',
            'content.contact.intro', 'contact.response_sla_hours', 'content.footer.text',
            'seo.home.title', 'seo.home.description', 'seo.contact.title', 'seo.contact.description',
            'legal.terms_content', 'legal.notices_content',
        ];
        foreach ($keys as $key) {
            self::assertTrue(ConfigurationRegistry::has($key), $key);
        }
    }

    public function testRuntimeDoesNotInventHistoricalEditorialClaimsOrFallbackAssets(): void
    {
        $root = dirname(__DIR__, 3);
        $runtime = '';
        foreach (['src/Controllers', 'src/Views'] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $runtime .= file_get_contents($file->getPathname()) ?: '';
                }
            }
        }
        foreach (['25 ans', 'sous 48h', 'hero-traiteur.webp', 'preparation-traiteur-generique.webp', 'Mon Traiteur'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $runtime, $forbidden);
        }
    }
}
