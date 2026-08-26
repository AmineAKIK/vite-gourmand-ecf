from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one marker, found {count}: {old[:100]!r}')
    p.write_text(text.replace(old, new, 1))


# PricingService: one canonical quote path for both pricing and public quote API.
p = Path('src/Services/PricingService.php')
text = p.read_text()
if text.count('use App\\Geo\\DeliveryResolver;\n') != 1:
    raise SystemExit('PricingService DeliveryResolver import marker mismatch')
text = text.replace('use App\\Geo\\DeliveryResolver;\n', '')
old = '''        $prixLivraisonCents = DeliveryResolver::computeDeliveryPriceCents($adresse, $ville, $codePostal);
        if ($prixLivraisonCents === null) {
            throw new InvalidArgumentException(
                'Adresse de livraison non reconnue ou incohérente avec la ville et le code postal.'
            );
        }

        $pricing = OrderPricingCalculator::calculate(
            $panierItems,
            $prixLivraisonCents,
'''
new = '''        $deliveryQuote = DeliveryQuoteService::quote($adresse, $ville, $codePostal);
        if ($deliveryQuote === null) {
            throw new InvalidArgumentException(
                'Adresse de livraison non reconnue ou incohérente avec la ville et le code postal.'
            );
        }

        $pricing = OrderPricingCalculator::calculate(
            $panierItems,
            $deliveryQuote['price_cents'],
'''
if text.count(old) != 1:
    raise SystemExit('PricingService delivery pricing marker mismatch')
text = text.replace(old, new, 1)
p.write_text(text)


# CommandeController: public quote and final order pricing share the same orchestration.
p = Path('src/Controllers/CommandeController.php')
text = p.read_text()
text = text.replace('use App\\Config\\Database;\n', 'use App\\Config\\ConfigurationIncompleteException;\nuse App\\Config\\Database;\n', 1)
if text.count('use App\\Geo\\Exception\\DeliveryGeoNotConfiguredException;\n') != 1:
    raise SystemExit('CommandeController legacy geo exception import marker mismatch')
text = text.replace('use App\\Geo\\Exception\\DeliveryGeoNotConfiguredException;\n', '')
text = text.replace(
    'use App\\Geo\\Exception\\DeliveryOutOfRangeException;\n',
    'use App\\Geo\\Exception\\DeliveryOutOfRangeException;\nuse App\\Geo\\Exception\\DeliveryProviderUnavailableException;\n',
    1,
)
text = text.replace(
    'use App\\Services\\CommandeService;\n',
    'use App\\Services\\CommandeService;\nuse App\\Services\\DeliveryQuoteService;\n',
    1,
)
old = '''        try {
            $prixCents = \\App\\Geo\\DeliveryResolver::computeDeliveryPriceCents($adresse, $ville, $codePostal);
        } catch (DeliveryGeoNotConfiguredException $e) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'message' => 'Le service de livraison n\\'est pas encore configuré. Contactez le traiteur.']);
            return;
        } catch (DeliveryOutOfRangeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'hors_rayon' => true]);
            return;
        }

        if ($prixCents === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Adresse non reconnue ou incohérente avec le code postal.']);
            return;
        }

        $adresseResolue = resolveAdresseLivraison($adresse, $ville, $codePostal);
        $distance = $adresseResolue
            ? distanceKmDepuisCoordonnees((float)$adresseResolue['lat'], (float)$adresseResolue['lng'])
            : null;

        echo json_encode([
            'ok'       => true,
            'distance' => $distance,
            'prix_cents' => $prixCents,
            'adresse'  => $adresseResolue['label'] ?? null,
        ]);
'''
new = '''        try {
            $quote = DeliveryQuoteService::quote($adresse, $ville, $codePostal);
        } catch (ConfigurationIncompleteException) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'message' => 'Le service de livraison n\\'est pas encore configuré. Contactez le traiteur.']);
            return;
        } catch (DeliveryProviderUnavailableException) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'message' => 'Le service de validation d\\'adresse est temporairement indisponible. Veuillez réessayer.']);
            return;
        } catch (DeliveryOutOfRangeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'hors_rayon' => true]);
            return;
        }

        if ($quote === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Adresse non reconnue ou incohérente avec le code postal.']);
            return;
        }

        echo json_encode([
            'ok' => true,
            'distance' => $quote['distance_km'],
            'prix_cents' => $quote['price_cents'],
            'adresse' => $quote['resolved']['label'] ?? null,
        ]);
'''
if text.count(old) != 1:
    raise SystemExit('CommandeController calculLivraison marker mismatch')
text = text.replace(old, new, 1)
old = '''        } catch (DeliveryOutOfRangeException $e) {
            flash('error', $e->getMessage());
            redirect('/panier');
        } catch (DeliveryGeoNotConfiguredException) {
            flash('error', 'Le service de livraison n\\'est pas encore configuré. Contactez le traiteur.');
            redirect('/panier');
        } catch (\\InvalidArgumentException $e) {
'''
new = '''        } catch (DeliveryOutOfRangeException $e) {
            flash('error', $e->getMessage());
            redirect('/panier');
        } catch (ConfigurationIncompleteException) {
            flash('error', 'Le service de livraison n\\'est pas encore configuré. Contactez le traiteur.');
            redirect('/panier');
        } catch (DeliveryProviderUnavailableException) {
            flash('error', 'Le service de validation d\\'adresse est temporairement indisponible. Veuillez réessayer.');
            redirect('/panier');
        } catch (\\InvalidArgumentException $e) {
'''
if text.count(old) != 1:
    raise SystemExit('CommandeController create delivery exception marker mismatch')
text = text.replace(old, new, 1)
p.write_text(text)


# Helpers: geography-only wrappers may remain only where consumed; business policy delegates to DeliveryPolicy.
p = Path('src/helpers.php')
text = p.read_text()
text = text.replace('use App\\Domain\\OrderStatus;\n', 'use App\\Domain\\DeliveryPolicy;\nuse App\\Domain\\OrderStatus;\n', 1)
for block in [
    '''function resolveAdresseLivraison(string $a, string $v, string $cp): ?array
{
    return DeliveryResolver::resolveAddress($a, $v, $cp);
}
''',
    '''function distanceKmDepuisCoordonnees(float $lat, float $lon): float
{
    return DeliveryResolver::distanceKmFromCoords($lat, $lon);
}
''',
]:
    if text.count(block) != 1:
        raise SystemExit(f'helpers geography block marker mismatch: {block.splitlines()[0]}')
    text = text.replace(block, '', 1)
for line in [
    "function siteLat(): float                                   { return SiteConfig::lat(); }\n",
    "function siteLng(): float                                   { return SiteConfig::lng(); }\n",
    "function sitePostalCodesFree(): array                       { return SiteConfig::freePostalCodes(); }\n",
    "function livraisonBaseCents(): int                          { return SiteConfig::deliveryBaseCents(); }\n",
    "function livraisonPerKmCents(): int                         { return SiteConfig::deliveryPerKmCents(); }\n",
    "function livraisonRayonMaxKm(): int                         { return SiteConfig::deliveryRadiusKm(); }\n",
    "function livraisonGeoConfigured(): bool                     { return SiteConfig::isGeoConfigured(); }\n",
]:
    if text.count(line) != 1:
        raise SystemExit(f'helpers policy line marker mismatch: {line.strip()}')
    text = text.replace(line, '', 1)
old = "function deliveryPricingLabel(): string                     { return SiteConfig::deliveryPricingLabel(); }\n"
new = "function deliveryPricingLabel(): string                     { return DeliveryPolicy::fromConfiguration()->pricingLabel(); }\n"
if text.count(old) != 1:
    raise SystemExit('helpers deliveryPricingLabel marker mismatch')
text = text.replace(old, new, 1)
p.write_text(text)


# SiteConfig stops being a second delivery policy facade.
p = Path('src/Config/SiteConfig.php')
text = p.read_text()
start = text.find('    public static function lat(): float\n')
end = text.find('    public static function discountThresholdCents(): int\n')
if start < 0 or end < 0 or end <= start:
    raise SystemExit('SiteConfig delivery policy section markers missing')
text = text[:start] + text[end:]
old = '''    public static function deliveryPricingLabel(): string
    {
        return 'Livraison gratuite à ' . self::city() . '. '
            . Money::toDecimalString(self::deliveryBaseCents()) . ' € + '
            . Money::toDecimalString(self::deliveryPerKmCents()) . ' €/km au-delà.';
    }

'''
if text.count(old) != 1:
    raise SystemExit('SiteConfig deliveryPricingLabel marker mismatch')
text = text.replace(old, '', 1)
p.write_text(text)


# Provider outage is its own integration failure, no longer disguised as missing configuration.
Path('src/Geo/Exception/DeliveryProviderUnavailableException.php').write_text(
    "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Geo\\Exception;\n\nfinal class DeliveryProviderUnavailableException extends \\RuntimeException {}\n"
)
legacy = Path('src/Geo/Exception/DeliveryGeoNotConfiguredException.php')
if not legacy.exists():
    raise SystemExit('legacy DeliveryGeoNotConfiguredException file missing')
legacy.unlink()


# Existing geo unit test follows the dedicated provider boundary.
Path('tests/Unit/Geo/DeliveryResolverTest.php').write_text('''<?php

declare(strict_types=1);

namespace Tests\\Unit\\Geo;

use App\\Geo\\DeliveryResolver;
use App\\Geo\\GeocodingProvider;
use PHPUnit\\Framework\\TestCase;

final class DeliveryResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        DeliveryResolver::useProviderForTests(null);
    }

    public function testNormalizeLabelIsStableForAddressComparison(): void
    {
        self::assertSame('saint etienne', DeliveryResolver::normalizeLabel("  Saint-Étienne  "));
        self::assertSame('l hay les roses', DeliveryResolver::normalizeLabel("L’Haÿ-les-Roses"));
    }

    public function testResolverDelegatesGeocodingToProviderBoundary(): void
    {
        $provider = new class implements GeocodingProvider {
            public function geocodeCity(string $city): ?array
            {
                return $city === 'Paris' ? [48.8566, 2.3522] : null;
            }

            public function resolveAddress(string $address, string $city, string $postalCode): ?array
            {
                return [
                    'label' => $address . ', ' . $postalCode . ' ' . $city,
                    'city' => $city,
                    'postcode' => $postalCode,
                    'lat' => 48.8566,
                    'lng' => 2.3522,
                    'score' => 1.0,
                    'fallback' => false,
                ];
            }
        };
        DeliveryResolver::useProviderForTests($provider);

        self::assertSame([48.8566, 2.3522], DeliveryResolver::geocodeCity('Paris'));
        self::assertSame(
            '1 rue de Rivoli, 75001 Paris',
            DeliveryResolver::resolveAddress('1 rue de Rivoli', 'Paris', '75001')['label'],
        );
    }
}
''')

print('phase4 delivery policy clean finalizer applied')

# Trigger commit after the workflow exists on the branch.
