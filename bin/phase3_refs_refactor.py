from pathlib import Path


def patch(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    if old not in text:
        raise SystemExit(f'missing snippet in {path}: {old[:100]!r}')
    p.write_text(text.replace(old, new, 1), encoding='utf-8')

# Tenant-owned order numbering policy.
needle = "            self::tenant('order.capacity.max_per_day', 'commandes_max_par_jour', ConfigurationType::INTEGER, false, $admin, 'orders', 'Capacité maximale de commandes par jour.', null, ['min' => 0, 'max' => 999]),"
insert = needle + "\n            self::tenant('order.number_prefix', 'commande_prefixe', ConfigurationType::STRING, true, $admin, 'orders', 'Préfixe public des références de commande.', null, ['max_length' => 12, 'pattern' => '/^[A-Za-z0-9]+$/']),"
patch('src/Config/ConfigurationRegistry.php', needle, insert)

for context in ['ordering', 'checkout']:
    p = Path('src/Config/ConfigurationCompleteness.php')
    text = p.read_text(encoding='utf-8')
    anchor = "            'order.capacity.max_per_day',"
    start = text.index("        '" + context + "' => [")
    end = text.index('        ],', start)
    segment = text[start:end]
    if "'order.number_prefix'" not in segment:
        segment2 = segment.replace(anchor, anchor + "\n            'order.number_prefix',", 1)
        text = text[:start] + segment2 + text[end:]
        p.write_text(text, encoding='utf-8')

Path('src/Services/OrderReferenceGenerator.php').write_text(r'''<?php

namespace App\Services;

use App\Config\Configuration;
use UnexpectedValueException;

final class OrderReferenceGenerator
{
    public static function generate(): string
    {
        $prefix = Configuration::get('order.number_prefix');
        if (!is_string($prefix) || $prefix === '') {
            throw new UnexpectedValueException('order.number_prefix must resolve to a non-empty string.');
        }

        return self::format($prefix, date('Ymd'), strtoupper(bin2hex(random_bytes(4))));
    }

    public static function format(string $prefix, string $date, string $entropy): string
    {
        $prefix = strtoupper(trim($prefix));
        if (preg_match('/^[A-Z0-9]{1,12}$/', $prefix) !== 1) {
            throw new UnexpectedValueException('Invalid order reference prefix.');
        }
        if (preg_match('/^\d{8}$/', $date) !== 1 || preg_match('/^[A-F0-9]{8}$/', $entropy) !== 1) {
            throw new UnexpectedValueException('Invalid order reference components.');
        }

        return $prefix . '-' . $date . '-' . $entropy;
    }
}
''', encoding='utf-8')

# Remove legacy global generator and route active consumer to service.
helpers = Path('src/helpers.php')
text = helpers.read_text(encoding='utf-8')
old = """function generateNumeroCommande(): string
{
    return 'VG-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Ymd');
}

"""
if old not in text: raise SystemExit('legacy generator missing')
helpers.write_text(text.replace(old, '', 1), encoding='utf-8')
patch('src/Controllers/CommandeController.php', 'use App\\Services\\OrderAdmissionService;', 'use App\\Services\\OrderAdmissionService;\nuse App\\Services\\OrderReferenceGenerator;')
patch('src/Controllers/CommandeController.php', '$numeroCommande = generateNumeroCommande();', '$numeroCommande = OrderReferenceGenerator::generate();')

# Semantic closed asset vocabulary.
Path('src/Domain/BrandAsset.php').write_text(r'''<?php

namespace App\Domain;

enum BrandAsset: string
{
    case LOGO = 'logo';
    case FAVICON = 'favicon';
    case OG_IMAGE = 'og_image';
    case HERO = 'hero';
    case PRESENTATION = 'preparation';

    /** @return list<string> */
    public static function storageKeys(): array
    {
        return array_map(static fn(self $asset): string => $asset->value, self::cases());
    }
}
''', encoding='utf-8')

Path('src/Models/SiteImageModel.php').write_text(r'''<?php

namespace App\Models;

use App\Config\Database;
use App\Domain\BrandAsset;

class SiteImageModel
{
    public static function get(BrandAsset $asset): ?string
    {
        $stmt = Database::getConnection()->prepare('SELECT url FROM site_image WHERE cle = ?');
        $stmt->execute([$asset->value]);
        $row = $stmt->fetch();
        return $row ? (string) $row['url'] : null;
    }

    /** @return array<string,string> */
    public static function getAll(): array
    {
        $allowed = BrandAsset::storageKeys();
        $stmt = Database::getConnection()->query('SELECT cle, url FROM site_image');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['cle'];
            if (in_array($key, $allowed, true)) {
                $result[$key] = (string) $row['url'];
            }
        }
        return $result;
    }

    public static function set(BrandAsset $asset, string $url): void
    {
        Database::getConnection()
            ->prepare('INSERT INTO site_image (cle, url) VALUES (?, ?) ON DUPLICATE KEY UPDATE url = ?, updated_at = NOW()')
            ->execute([$asset->value, $url, $url]);
    }
}
''', encoding='utf-8')

# Consumers use enum values, never free asset keys.
patch('src/Config/SiteConfig.php', 'use App\\Models\\SiteConfigModel;', 'use App\\Domain\\BrandAsset;\nuse App\\Models\\SiteConfigModel;')
patch('src/Config/SiteConfig.php', "SiteImageModel::get('logo')", 'SiteImageModel::get(BrandAsset::LOGO)')

home = Path('src/Controllers/HomeController.php')
text = home.read_text(encoding='utf-8')
text = text.replace('use App\\Config\\Configuration;', 'use App\\Config\\Configuration;\nuse App\\Domain\\BrandAsset;')
text = text.replace("$heroUrl = isset($siteImages['hero']) && $siteImages['hero'] !== ''\n            ? imageUrl($siteImages['hero'], '')\n            : null;", "$heroUrl = isset($siteImages[BrandAsset::HERO->value]) && $siteImages[BrandAsset::HERO->value] !== ''\n            ? imageUrl($siteImages[BrandAsset::HERO->value], '')\n            : null;")
home.write_text(text, encoding='utf-8')

for path in ['src/Views/layouts/main.php', 'src/Views/layouts/workspace.php']:
    p = Path(path); text = p.read_text(encoding='utf-8')
    text = text.replace("\\App\\Models\\SiteImageModel::get('favicon')", '\\App\\Models\\SiteImageModel::get(\\App\\Domain\\BrandAsset::FAVICON)')
    text = text.replace("\\App\\Models\\SiteImageModel::get('og_image')", '\\App\\Models\\SiteImageModel::get(\\App\\Domain\\BrandAsset::OG_IMAGE)')
    p.write_text(text, encoding='utf-8')

admin = Path('src/Controllers/Admin/ParametresController.php')
text = admin.read_text(encoding='utf-8')
text = text.replace('use App\\Models\\HoraireModel;', 'use App\\Domain\\BrandAsset;\nuse App\\Models\\HoraireModel;')
old_loop = """        foreach (['logo', 'favicon', 'og_image', 'hero', 'preparation'] as $cle) {
            $file = $_FILES[$cle] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $url = MenuAdminService::uploadSiteImage($file, 'site/' . $cle);
            if ($url) {
                SiteImageModel::set($cle, $url);
            } else {
                flash('error', 'Erreur lors de l\\'upload de l\\'image "' . $cle . '".');
                redirect('/admin/parametres#personnalisation');
            }
        }"""
new_loop = """        foreach (BrandAsset::cases() as $asset) {
            $file = $_FILES[$asset->value] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $url = MenuAdminService::uploadSiteImage($file, 'site/' . $asset->value);
            if ($url) {
                SiteImageModel::set($asset, $url);
            } else {
                flash('error', 'Erreur lors de l\\'upload de l\\'image "' . $asset->value . '".');
                redirect('/admin/parametres#personnalisation');
            }
        }"""
if old_loop not in text: raise SystemExit('asset upload loop missing')
admin.write_text(text.replace(old_loop, new_loop, 1), encoding='utf-8')

# Admin can configure order reference prefix explicitly.
view = Path('src/Views/pages/admin/parametres.php')
text = view.read_text(encoding='utf-8')
anchor = '''                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="commandes_max_par_jour">Commandes max / jour</label>'''
field = '''                        <div class="col-6 col-lg-3">
                            <label class="form-label fw-medium" for="commande_prefixe">Préfixe des commandes</label>
                            <input type="text" class="form-control text-uppercase" id="commande_prefixe" name="commande_prefixe"
                                   value="<?= $cfg('commande_prefixe') ?>" maxlength="12" pattern="[A-Za-z0-9]+" required>
                            <div class="form-text">Exemple : ACME. Utilisé dans les références publiques.</div>
                        </div>
''' + anchor
if anchor not in text: raise SystemExit('order capacity admin anchor missing')
view.write_text(text.replace(anchor, field, 1), encoding='utf-8')

Path('tests/Unit/WhiteLabel/ReferenceAndAssetContractTest.php').write_text(r'''<?php

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
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'css'], true)) continue;
                $runtime .= file_get_contents($file->getPathname()) ?: '';
            }
        }
        self::assertStringNotContainsString("'VG-'", $runtime);
        self::assertStringNotContainsString('generateNumeroCommande', $runtime);
        self::assertStringNotContainsString("SiteImageModel::get('", $runtime);
        self::assertStringNotContainsString("SiteImageModel::set($", $runtime);
    }
}
''', encoding='utf-8')
