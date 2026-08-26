from pathlib import Path


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f'missing marker in {path}: {old[:120]!r}')
    p.write_text(text.replace(old, new, count))

# PricingService delegates the complete delivery quote to the policy orchestrator.
p = Path('src/Services/PricingService.php')
text = p.read_text()
text = text.replace('use App\\Geo\\DeliveryResolver;\n', '')
text = text.replace("        $prixLivraisonCents = DeliveryResolver::computeDeliveryPriceCents($adresse, $ville, $codePostal);\n        if ($prixLivraisonCents === null) {",
                    "        $deliveryQuote = DeliveryQuoteService::quote($adresse, $ville, $codePostal);\n        if ($deliveryQuote === null) {")
text = text.replace("        $pricing = OrderPricingCalculator::calculate(\n            $panierItems,\n            $prixLivraisonCents,",
                    "        $pricing = OrderPricingCalculator::calculate(\n            $panierItems,\n            $deliveryQuote['price_cents'],")
p.write_text(text)

# Controller uses the same quote as order pricing; no duplicate geocode/distance calculation.
p = Path('src/Controllers/CommandeController.php')
text = p.read_text()
text = text.replace('use App\\Geo\\Exception\\DeliveryGeoNotConfiguredException;\n', 'use App\\Config\\ConfigurationIncompleteException;\n')
text = text.replace('use App\\Services\\CommandeService;\n', 'use App\\Services\\CommandeService;\nuse App\\Services\\DeliveryQuoteService;\n')
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
if old not in text:
    raise SystemExit('controller delivery quote marker missing')
text = text.replace(old, new)
text = text.replace('''        } catch (DeliveryGeoNotConfiguredException) {
            flash('error', 'Le service de livraison n\\'est pas encore configuré. Contactez le traiteur.');
            redirect('/panier');
''', '''        } catch (ConfigurationIncompleteException) {
            flash('error', 'Le service de livraison n\\'est pas encore configuré. Contactez le traiteur.');
            redirect('/panier');
''')
p.write_text(text)

# Helpers may expose geography utilities, never tenant delivery policy values.
p = Path('src/helpers.php')
text = p.read_text()
text = text.replace('''function distanceKmDepuisCoordonnees(float $lat, float $lon): float
{
    return DeliveryResolver::distanceKmFromCoords($lat, $lon);
}
''', '')
for line in [
    "function siteLat(): float                                   { return SiteConfig::lat(); }\n",
    "function siteLng(): float                                   { return SiteConfig::lng(); }\n",
    "function sitePostalCodesFree(): array                       { return SiteConfig::freePostalCodes(); }\n",
    "function livraisonBaseCents(): int                          { return SiteConfig::deliveryBaseCents(); }\n",
    "function livraisonPerKmCents(): int                         { return SiteConfig::deliveryPerKmCents(); }\n",
    "function livraisonRayonMaxKm(): int                         { return SiteConfig::deliveryRadiusKm(); }\n",
    "function livraisonGeoConfigured(): bool                     { return SiteConfig::isGeoConfigured(); }\n",
    "function deliveryPricingLabel(): string                     { return SiteConfig::deliveryPricingLabel(); }\n",
]:
    text = text.replace(line, '')
p.write_text(text)

print('delivery policy finalizer applied')
