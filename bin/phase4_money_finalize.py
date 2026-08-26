from pathlib import Path


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f"missing marker in {path}: {old[:120]!r}")
    p.write_text(text.replace(old, new, count))

# Exact-money configuration types.
replace('src/Config/ConfigurationRegistry.php',
        "self::tenant('delivery.base_fee', 'livraison_base', ConfigurationType::DECIMAL",
        "self::tenant('delivery.base_fee', 'livraison_base', ConfigurationType::MONEY")
replace('src/Config/ConfigurationRegistry.php',
        "self::tenant('delivery.per_km_fee', 'livraison_km', ConfigurationType::DECIMAL",
        "self::tenant('delivery.per_km_fee', 'livraison_km', ConfigurationType::MONEY")
replace('src/Config/ConfigurationRegistry.php',
        "self::tenant('payment.recovery_fee', 'indemnite_recouvrement', ConfigurationType::DECIMAL",
        "self::tenant('payment.recovery_fee', 'indemnite_recouvrement', ConfigurationType::MONEY")

# SiteConfig exposes monetary values only as minor units.
p = Path('src/Config/SiteConfig.php')
text = p.read_text()
text = text.replace('use App\\Domain\\BrandAsset;\n', 'use App\\Domain\\BrandAsset;\nuse App\\Domain\\Money;\n')
old = '''    public static function deliveryBase(): float
    {
        return self::requiredFloat('delivery.base_fee');
    }

    public static function deliveryKm(): float
    {
        return self::requiredFloat('delivery.per_km_fee');
    }

    public static function discountThreshold(): float
    {
        return self::requiredFloat('discount.threshold');
    }

    public static function discountRate(): float
    {
        return self::requiredFloat('discount.rate_percent');
    }
'''
new = '''    public static function deliveryBaseCents(): int
    {
        return Money::fromDecimal(self::requiredString('delivery.base_fee'));
    }

    public static function deliveryPerKmCents(): int
    {
        return Money::fromDecimal(self::requiredString('delivery.per_km_fee'));
    }

    public static function discountThresholdCents(): int
    {
        return Money::fromDecimal(self::requiredString('discount.threshold'));
    }

    public static function discountRatePercent(): int
    {
        return self::requiredInt('discount.rate_percent');
    }
'''
if old not in text:
    raise SystemExit('SiteConfig money methods marker missing')
text = text.replace(old, new)
text = text.replace("            . number_format(self::deliveryBase(), 2, ',', ' ') . ' € + '\n            . number_format(self::deliveryKm(), 2, ',', ' ') . ' €/km au-delà.';",
                    "            . Money::toDecimalString(self::deliveryBaseCents()) . ' € + '\n            . Money::toDecimalString(self::deliveryPerKmCents()) . ' €/km au-delà.';")
p.write_text(text)

# Delivery quote: money in cents, distance remains a measurement.
p = Path('src/Geo/DeliveryResolver.php')
text = p.read_text()
text = text.replace("            return ['price' => 0.0, 'distance' => $distance, 'resolved' => $resolved];",
                    "            return ['price_cents' => 0, 'distance' => $distance, 'resolved' => $resolved];")
text = text.replace("        return [\n            'price' => round(SiteConfig::deliveryBase() + (SiteConfig::deliveryKm() * $distance), 2),\n            'distance' => $distance,\n            'resolved' => $resolved,\n        ];",
                    "        $distanceHundredthsKm = (int) round($distance * 100);\n        $variableCents = intdiv((SiteConfig::deliveryPerKmCents() * $distanceHundredthsKm) + 50, 100);\n\n        return [\n            'price_cents' => SiteConfig::deliveryBaseCents() + $variableCents,\n            'distance' => $distance,\n            'resolved' => $resolved,\n        ];")
text = text.replace("    public static function computeDeliveryPrice(string $adresse, string $ville, string $codePostal): ?float\n    {\n        $quote = self::deliveryQuote($adresse, $ville, $codePostal);\n        return $quote !== null ? (float) $quote['price'] : null;\n    }",
                    "    public static function computeDeliveryPriceCents(string $adresse, string $ville, string $codePostal): ?int\n    {\n        $quote = self::deliveryQuote($adresse, $ville, $codePostal);\n        return $quote !== null ? (int) $quote['price_cents'] : null;\n    }")
p.write_text(text)

# Pricing service contract: exact cents and basis points, persistence-ready keys.
p = Path('src/Services/PricingService.php')
text = p.read_text()
text = text.replace("        $tauxTvaMenu = self::defaultTauxTvaByCategorie('menu');\n        $tauxTvaLivraison = self::defaultTauxTvaByCategorie('livraison');",
                    "        $tauxTvaMenuBasisPoints = self::defaultTauxTvaBasisPointsByCategorie('menu');\n        $tauxTvaLivraisonBasisPoints = self::defaultTauxTvaBasisPointsByCategorie('livraison');")
text = text.replace("        $prixLivraison = DeliveryResolver::computeDeliveryPrice($adresse, $ville, $codePostal);\n        if ($prixLivraison === null)",
                    "        $prixLivraisonCents = DeliveryResolver::computeDeliveryPriceCents($adresse, $ville, $codePostal);\n        if ($prixLivraisonCents === null)")
text = text.replace("            Money::fromDecimal($prixLivraison),", "            $prixLivraisonCents,")
text = text.replace("                'prix_par_personne_cents' => $line['prix_par_personne_cents'],\n                'prix_menu_brut_cents' => $line['prix_menu_brut_cents'],\n                'prix_menu_net_cents' => $line['prix_menu_net_cents'],",
                    "                'prix_par_personne_snapshot_cents' => $line['prix_par_personne_cents'],\n                'prix_menu_cents' => $line['prix_menu_net_cents'],")
text = text.replace("                'taux_tva_basis_points' => $tauxTvaMenu,\n                'taux_tva_id' => $tauxTvaMenuId,",
                    "                'taux_tva_menu_basis_points' => $tauxTvaMenuBasisPoints,\n                'taux_tva_livraison_basis_points' => $tauxTvaLivraisonBasisPoints,\n                'taux_tva_menu_id' => $tauxTvaMenuId,\n                'taux_tva_livraison_id' => $tauxTvaLivraisonId,")
text = text.replace("                'taux_tva_menu' => $tauxTvaMenu,", "                'taux_tva_menu_basis_points' => $tauxTvaMenuBasisPoints,")
text = text.replace("                'taux_tva_livraison' => $tauxTvaLivraison,", "                'taux_tva_livraison_basis_points' => $tauxTvaLivraisonBasisPoints,")
old_method = '''    public static function defaultTauxTvaByCategorie(string $categorie): float
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT taux FROM taux_tva WHERE categorie = ? AND par_defaut = 1 AND actif = 1 LIMIT 1'
        );
        $stmt->execute([$categorie]);
        $taux = $stmt->fetchColumn();
        if ($taux === false) {
            throw new RuntimeException('configuration_incomplete:tax_rate:' . $categorie);
        }

        return (float) $taux;
    }
'''
new_method = '''    public static function defaultTauxTvaBasisPointsByCategorie(string $categorie): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT taux FROM taux_tva WHERE categorie = ? AND par_defaut = 1 AND actif = 1 LIMIT 1'
        );
        $stmt->execute([$categorie]);
        $taux = $stmt->fetchColumn();
        if ($taux === false) {
            throw new RuntimeException('configuration_incomplete:tax_rate:' . $categorie);
        }

        return Money::percentToBasisPoints((string) $taux);
    }
'''
if old_method not in text:
    raise SystemExit('PricingService TVA method marker missing')
text = text.replace(old_method, new_method)
text = text.replace("     * historiques en euros restent présents pour compatibilité avec la base et les vues.\n", "     * Aucune valeur monétaire transactionnelle n'est exposée en float.\n")
p.write_text(text)

# Persistence writes the exact PricingService DTO and both tax snapshots.
p = Path('src/Models/CommandeModel.php')
text = p.read_text()
text = text.replace('''     *   prix_par_personne_snapshot_cents, taux_tva_basis_points, taux_reduction_basis_points,
     *   remise_appliquee_cents, taux_tva_id
''', '''     *   prix_par_personne_snapshot_cents, taux_tva_menu_basis_points, taux_tva_livraison_basis_points,
     *   taux_reduction_basis_points, remise_appliquee_cents, taux_tva_menu_id, taux_tva_livraison_id
''')
text = text.replace('''                    prix_par_personne_snapshot_cents, taux_tva_basis_points,
                    taux_reduction_basis_points, remise_appliquee_cents, taux_tva_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
''', '''                    prix_par_personne_snapshot_cents,
                    taux_tva_menu_basis_points, taux_tva_livraison_basis_points,
                    taux_reduction_basis_points, remise_appliquee_cents,
                    taux_tva_menu_id, taux_tva_livraison_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
''')
text = text.replace("                    (int)($ligne['taux_tva_basis_points'] ?? 0),\n                    (int)($ligne['taux_reduction_basis_points'] ?? 0),\n                    (int)($ligne['remise_appliquee_cents'] ?? 0),\n                    isset($ligne['taux_tva_id']) ? (int)$ligne['taux_tva_id'] : null,",
                    "                    (int)$ligne['taux_tva_menu_basis_points'],\n                    (int)$ligne['taux_tva_livraison_basis_points'],\n                    (int)$ligne['taux_reduction_basis_points'],\n                    (int)$ligne['remise_appliquee_cents'],\n                    isset($ligne['taux_tva_menu_id']) ? (int)$ligne['taux_tva_menu_id'] : null,\n                    isset($ligne['taux_tva_livraison_id']) ? (int)$ligne['taux_tva_livraison_id'] : null,")
p.write_text(text)

# Delivery endpoint returns cents only.
p = Path('src/Controllers/CommandeController.php')
text = p.read_text()
text = text.replace("$prix = \\App\\Geo\\DeliveryResolver::computeDeliveryPrice($adresse, $ville, $codePostal);",
                    "$prixCents = \\App\\Geo\\DeliveryResolver::computeDeliveryPriceCents($adresse, $ville, $codePostal);")
text = text.replace('if ($prix === null)', 'if ($prixCents === null)', 1)
text = text.replace("            'prix'     => $prix,", "            'prix_cents' => $prixCents,")
p.write_text(text)

# Payment summaries are cents-only and payment status compares integers exactly.
p = Path('src/Models/PaiementModel.php')
text = p.read_text()
text = text.replace("            'total_encaisse' => 0.00,\n            'total_acomptes' => 0.00,\n            'total_soldes' => 0.00,\n            'total_paiements_uniques' => 0.00,\n            'total_rembourse' => 0.00,",
                    "            'total_encaisse_cents' => 0,\n            'total_acomptes_cents' => 0,\n            'total_soldes_cents' => 0,\n            'total_paiements_uniques_cents' => 0,\n            'total_rembourse_cents' => 0,")
text = text.replace("    public static function statutPaiement(float $totalEncaisse, float $prixTotal): string\n    {\n        if ($prixTotal <= 0) {",
                    "    public static function statutPaiement(int $totalEncaisseCents, int $prixTotalCents): string\n    {\n        if ($prixTotalCents <= 0) {")
text = text.replace("        if ($totalEncaisse >= $prixTotal - 0.01) {", "        if ($totalEncaisseCents >= $prixTotalCents) {")
text = text.replace("        if ($totalEncaisse > 0) {", "        if ($totalEncaisseCents > 0) {")
p.write_text(text)

# Remove old money helper surface; presentation gets cents explicitly.
p = Path('src/helpers.php')
text = p.read_text()
for line in [
    "function livraisonBase(): float                             { return SiteConfig::deliveryBase(); }\n",
    "function livraisonKm(): float                               { return SiteConfig::deliveryKm(); }\n",
    "function reductionSeuilMontant(): float                     { return SiteConfig::discountThreshold(); }\n",
    "function reductionTauxPourcentage(): float                  { return SiteConfig::discountRate(); }\n",
]:
    text = text.replace(line, '')
text = text.replace("function livraisonRayonMaxKm(): int", "function livraisonBaseCents(): int                          { return SiteConfig::deliveryBaseCents(); }\nfunction livraisonPerKmCents(): int                         { return SiteConfig::deliveryPerKmCents(); }\nfunction reductionSeuilCents(): int                         { return SiteConfig::discountThresholdCents(); }\nfunction reductionTauxPourcentage(): int                    { return SiteConfig::discountRatePercent(); }\nfunction livraisonRayonMaxKm(): int")
p.write_text(text)

# Cart presentation and JS use integer cents end-to-end.
p = Path('src/Views/pages/panier/index.php')
text = p.read_text()
text = text.replace("<?= sanitize(formatPrice(round((float) $item['prix_par_personne'] * (int) $item['nombre_personne'], 2))) ?>",
                    "<?= sanitize(formatMoneyCents(\\App\\Domain\\Money::fromDecimal((string) $item['prix_par_personne']) * (int) $item['nombre_personne'])) ?>")
text = text.replace("                    $totalBrut = 0.0;\n                    foreach ($panier as $item) {\n                        $totalBrut += round((float) $item['prix_par_personne'] * (int) $item['nombre_personne'], 2);\n                    }\n                    $totalBrut = round($totalBrut, 2);",
                    "                    $totalBrutCents = 0;\n                    foreach ($panier as $item) {\n                        $totalBrutCents += \\App\\Domain\\Money::fromDecimal((string) $item['prix_par_personne']) * (int) $item['nombre_personne'];\n                    }")
text = text.replace("<?php $prixLigne = round((float) $item['prix_par_personne'] * (int) $item['nombre_personne'], 2); ?>",
                    "<?php $prixLigneCents = \\App\\Domain\\Money::fromDecimal((string) $item['prix_par_personne']) * (int) $item['nombre_personne']; ?>")
text = text.replace("<?= sanitize(formatPrice($prixLigne)) ?>", "<?= sanitize(formatMoneyCents($prixLigneCents)) ?>")
text = text.replace("<?= sanitize(formatPrice($totalBrut)) ?>", "<?= sanitize(formatMoneyCents($totalBrutCents)) ?>")
text = text.replace("const totalBrut = <?= json_encode($totalBrut) ?>;\nconst reductionSeuil = <?= json_encode(reductionSeuilMontant()) ?>;\nconst reductionTaux = <?= json_encode(reductionTauxPourcentage() / 100) ?>;",
                    "const totalBrutCents = <?= json_encode($totalBrutCents) ?>;\nconst reductionSeuilCents = <?= json_encode(reductionSeuilCents()) ?>;\nconst reductionTauxBasisPoints = <?= json_encode(reductionTauxPourcentage() * 100) ?>;")
text = text.replace("        const livraison = parseFloat(data.prix);", "        const livraisonCents = Number(data.prix_cents);")
text = text.replace("        let remise = 0;\n        if (reductionSeuil > 0 && totalBrut >= reductionSeuil) {\n            remise = Math.round(totalBrut * reductionTaux * 100) / 100;\n            document.getElementById('recap-remise').textContent = '-' + remise.toFixed(2) + ' €';",
                    "        let remiseCents = 0;\n        if (reductionSeuilCents > 0 && totalBrutCents >= reductionSeuilCents) {\n            remiseCents = Math.round(totalBrutCents * reductionTauxBasisPoints / 10000);\n            document.getElementById('recap-remise').textContent = '-' + (remiseCents / 100).toFixed(2) + ' €';")
text = text.replace("        document.getElementById('recap-livraison').textContent = livraison.toFixed(2) + ' €';\n        document.getElementById('recap-total').textContent = (totalBrut - remise + livraison).toFixed(2) + ' €';",
                    "        document.getElementById('recap-livraison').textContent = (livraisonCents / 100).toFixed(2) + ' €';\n        document.getElementById('recap-total').textContent = ((totalBrutCents - remiseCents + livraisonCents) / 100).toFixed(2) + ' €';")
p.write_text(text)

# Stats controller converts cents only at CSV presentation boundary.
p = Path('src/Controllers/Admin/StatsController.php')
text = p.read_text()
replacements = {
    "number_format((float)$row['total_ht'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$row['total_ht_cents'])",
    "number_format((float)$row['total_tva'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$row['total_tva_cents'])",
    "number_format((float)$row['total_ttc'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$row['total_ttc_cents'])",
    "number_format((float)$row['montant_encaisse'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$row['montant_encaisse_cents'])",
    "number_format((float)$row['solde_restant'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$row['solde_restant_cents'])",
    "number_format((float)$r['prix_brut_menu'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['prix_brut_menu_cents'])",
    "number_format((float)$r['remise'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['remise_cents'])",
    "number_format((float)$r['prix_net_menu'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['prix_net_menu_cents'])",
    "number_format((float)$r['frais_livraison'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['frais_livraison_cents'])",
    "number_format((float)$r['total_ligne_ttc'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['total_ligne_ttc_cents'])",
    "number_format((float)$r['taux_tva'], 2, ',', '')": "number_format(((int)$r['taux_tva_menu_basis_points']) / 100, 2, ',', '')",
    "number_format((float)$r['total_ligne_ht'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['total_ligne_ht_cents'])",
    "number_format((float)$r['tva_ligne'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['tva_ligne_cents'])",
    "number_format((float)$r['panier_moyen_ttc'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['panier_moyen_ttc_cents'])",
    "number_format((float)$r['ca_ttc'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['ca_ttc_cents'])",
    "number_format((float)$r['ca_ht'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['ca_ht_cents'])",
    "number_format((float)$r['tva_collectee'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['tva_collectee_cents'])",
    "number_format((float)$r['total_ttc'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['total_ttc_cents'])",
    "number_format((float)$r['total_ht'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['total_ht_cents'])",
    "number_format((float)$r['total_tva'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['total_tva_cents'])",
    "number_format((float)$r['montant_encaisse'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['montant_encaisse_cents'])",
    "number_format((float)$r['solde_restant'], 2, ',', '')": "\\App\\Domain\\Money::toDecimalString((int)$r['solde_restant_cents'])",
}
for old, new in replacements.items():
    text = text.replace(old, new)
p.write_text(text)

# Finance dashboard keeps cents for sums/averages; convert only chart coordinates to decimal numbers.
p = Path('src/Views/pages/admin/finances.php')
text = p.read_text()
text = text.replace("$totalTTC     = (float)($synthese['total_ttc']        ?? 0);\n$totalHT      = (float)($synthese['total_ht']         ?? 0);\n$totalTVA     = (float)($synthese['total_tva']        ?? 0);\n$totalNb      = (int)($synthese['nb_commandes']       ?? 0);\n$encaisse     = (float)($synthese['montant_encaisse'] ?? 0);\n$soldeRestant = (float)($synthese['solde_restant']    ?? 0);\n$panierMoyen  = $totalNb > 0 ? $totalTTC / $totalNb : 0;\n\n$menuSalesTtc = array_sum(array_map(fn($row) => (float)($row['ca'] ?? 0), $caStats ?? []));\n$menuSalesHt  = array_sum(array_map(fn($row) => (float)($row['ca_ht'] ?? 0), $caStats ?? []));",
                    "$totalTtcCents = (int)($synthese['total_ttc_cents'] ?? 0);\n$totalHtCents = (int)($synthese['total_ht_cents'] ?? 0);\n$totalTvaCents = (int)($synthese['total_tva_cents'] ?? 0);\n$totalNb = (int)($synthese['nb_commandes'] ?? 0);\n$encaisseCents = (int)($synthese['montant_encaisse_cents'] ?? 0);\n$soldeRestantCents = (int)($synthese['solde_restant_cents'] ?? 0);\n$panierMoyenCents = $totalNb > 0 ? (int) round($totalTtcCents / $totalNb) : 0;\n\n$menuSalesTtc = array_sum(array_map(fn($row) => (int)($row['ca_cents'] ?? 0), $caStats ?? []));\n$menuSalesHt  = array_sum(array_map(fn($row) => (int)($row['ca_ht_cents'] ?? 0), $caStats ?? []));")
text = text.replace("? ((float)$topMenu['ca'] / $menuSalesTtc) * 100", "? ((int)$topMenu['ca_cents'] / $menuSalesTtc) * 100")
text = text.replace("$chartData = array_map(fn($row) => round((float)($row['ca'] ?? 0), 2), $caStats ?? []);", "$chartData = array_map(fn($row) => ((int)($row['ca_cents'] ?? 0)) / 100, $caStats ?? []);")
text = text.replace("$chartMensuelData = array_map(fn($row) => round((float)($row['ca_ttc'] ?? 0), 2), $mensuelAsc);", "$chartMensuelData = array_map(fn($row) => ((int)($row['ca_ttc_cents'] ?? 0)) / 100, $mensuelAsc);")
for old, new in {
    'formatPrice($totalTTC)': 'formatMoneyCents($totalTtcCents)',
    'formatPrice($totalHT)': 'formatMoneyCents($totalHtCents)',
    'formatPrice($totalTVA)': 'formatMoneyCents($totalTvaCents)',
    'formatPrice($menuSalesTtc)': 'formatMoneyCents($menuSalesTtc)',
    'formatPrice($panierMoyen)': 'formatMoneyCents($panierMoyenCents)',
    'formatPrice($menuSalesHt)': 'formatMoneyCents($menuSalesHt)',
    'formatPrice($menuSalesTva)': 'formatMoneyCents($menuSalesTva)',
}.items():
    text = text.replace(old, new)
text = text.replace("$ca = (float)($row['ca'] ?? 0);\n                                    $caHT = (float)($row['ca_ht'] ?? 0);",
                    "$ca = (int)($row['ca_cents'] ?? 0);\n                                    $caHT = (int)($row['ca_ht_cents'] ?? 0);")
text = text.replace('formatPrice($average)', 'formatMoneyCents((int) round($average))')
text = text.replace('formatPrice($caHT)', 'formatMoneyCents($caHT)')
text = text.replace('formatPrice($tva)', 'formatMoneyCents($tva)')
text = text.replace('formatPrice($ca)', 'formatMoneyCents($ca)')
# Remaining dashboard summary variables.
text = text.replace('formatPrice($encaisse)', 'formatMoneyCents($encaisseCents)')
text = text.replace('formatPrice($soldeRestant)', 'formatMoneyCents($soldeRestantCents)')
# Monthly row fields.
text = text.replace("formatPrice($row['panier_moyen_ttc'] ?? 0)", "formatMoneyCents((int)($row['panier_moyen_ttc_cents'] ?? 0))")
text = text.replace("formatPrice($row['ca_ht'] ?? 0)", "formatMoneyCents((int)($row['ca_ht_cents'] ?? 0))")
text = text.replace("formatPrice($row['tva_collectee'] ?? 0)", "formatMoneyCents((int)($row['tva_collectee_cents'] ?? 0))")
text = text.replace("formatPrice($row['ca_ttc'] ?? 0)", "formatMoneyCents((int)($row['ca_ttc_cents'] ?? 0))")
p.write_text(text)

# Billing remains a separate aggregate (Phase 7) but its order snapshot boundary converts
# canonical cents/basis points explicitly instead of treating them as decimal euros/percentages.
p = Path('src/Models/FacturationModel.php')
text = p.read_text()
text = text.replace('use App\\Config\\SiteConfig;\n', 'use App\\Config\\SiteConfig;\nuse App\\Domain\\Money;\nuse App\\Domain\\BusinessPolicy;\nuse App\\Config\\Configuration;\n')
text = text.replace("        $tauxTvaLivraison = PricingService::defaultTauxTvaByCategorie('livraison');",
                    "        $tauxTvaLivraison = PricingService::defaultTauxTvaBasisPointsByCategorie('livraison') / 100;")
text = text.replace("            $tauxTvaMenu = (float)($ligne['taux_tva_basis_points'] ?? 0) > 0\n                ? (float)$ligne['taux_tva_basis_points']\n                : PricingService::defaultTauxTvaByCategorie('menu');",
                    "            $tauxTvaMenu = ((int)($ligne['taux_tva_menu_basis_points'] ?? 0)) / 100;")
text = text.replace("            $prixParPers = (float)($ligne['prix_par_personne_snapshot_cents'] ?? 0) > 0\n                ? (float)$ligne['prix_par_personne_snapshot_cents']\n                : (float)($ligne['prix_par_personne'] ?? 0);\n            $menuBrutTtc = round($prixParPers * $nbPersonnes, 2);",
                    "            $prixParPersCents = (int)($ligne['prix_par_personne_snapshot_cents'] ?? 0);\n            $menuBrutTtc = (float) Money::toDecimalString($prixParPersCents * $nbPersonnes);")
text = text.replace("            $menuNetTtc = (float)($ligne['prix_menu_cents'] ?? 0);", "            $menuNetTtc = (float) Money::toDecimalString((int)($ligne['prix_menu_cents'] ?? 0));")
text = text.replace("            $remiseTtc = (float)($ligne['remise_appliquee_cents'] ?? 0) > 0\n                ? (float)$ligne['remise_appliquee_cents']\n                : round($menuBrutTtc - $menuNetTtc, 2);",
                    "            $remiseTtc = (float) Money::toDecimalString((int)($ligne['remise_appliquee_cents'] ?? 0));")
text = text.replace("                $tauxReduction = (float)($ligne['taux_reduction_basis_points'] ?? 0);", "                $tauxReduction = ((int)($ligne['taux_reduction_basis_points'] ?? 0)) / 100;")
text = text.replace("            $livraisonTtc = (float)($ligne['prix_livraison_cents'] ?? 0);", "            $livraisonTtc = (float) Money::toDecimalString((int)($ligne['prix_livraison_cents'] ?? 0));")
text = text.replace("                . 'Ce devis est valable 30 jours à compter de sa date d\\'émission.'",
                    "                . 'Ce devis est valable ' . (new BusinessPolicy(static fn(string $key): mixed => Configuration::get($key)))->quoteValidityDays() . ' jours à compter de sa date d\\'émission.'")
p.write_text(text)

# Stats queries follow the split tax columns.
p = Path('src/Services/StatsService.php')
text = p.read_text().replace('s.taux_tva_basis_points', 's.taux_tva_menu_basis_points')
p.write_text(text)

print('Phase 4 money finalizer applied')
