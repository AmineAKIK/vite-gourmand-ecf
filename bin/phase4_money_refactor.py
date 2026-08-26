from pathlib import Path
import re


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f'missing marker in {path}: {old[:100]!r}')
    p.write_text(text.replace(old, new, count))

# Exact money-valued tenant configuration for the discount threshold.
replace('src/Config/ConfigurationDefinition.php', 'namespace App\\Config;\n\nuse InvalidArgumentException;', 'namespace App\\Config;\n\nuse App\\Domain\\Money;\nuse InvalidArgumentException;')
replace('src/Config/ConfigurationDefinition.php', '            ConfigurationType::DECIMAL,\n            ConfigurationType::COORDINATE => $this->normalizeDecimal($candidate),', '            ConfigurationType::DECIMAL,\n            ConfigurationType::COORDINATE => $this->normalizeDecimal($candidate),\n            ConfigurationType::MONEY => $this->normalizeMoney($candidate),')
replace('src/Config/ConfigurationDefinition.php', '    private function normalizeBoolean(string $candidate): bool\n', "    private function normalizeMoney(string $candidate): string\n    {\n        $cents = Money::fromDecimal($candidate);\n        $min = $this->constraints['min'] ?? null;\n        $max = $this->constraints['max'] ?? null;\n        if ($min !== null && $cents < Money::fromDecimal((string) $min)) {\n            throw new InvalidArgumentException('Configuration below minimum: ' . $this->key);\n        }\n        if ($max !== null && $cents > Money::fromDecimal((string) $max)) {\n            throw new InvalidArgumentException('Configuration above maximum: ' . $this->key);\n        }\n\n        return Money::toDecimalString($cents);\n    }\n\n    private function normalizeBoolean(string $candidate): bool\n")

p = Path('src/Config/ConfigurationRegistry.php')
text = p.read_text().replace("self::tenant('discount.threshold', 'reduction_seuil', ConfigurationType::DECIMAL", "self::tenant('discount.threshold', 'reduction_seuil', ConfigurationType::MONEY")
p.write_text(text)

# Presentation formatter for canonical minor units.
replace('src/Core/Formatter.php', 'namespace App\\Core;\n\nclass Formatter', 'namespace App\\Core;\n\nuse App\\Domain\\Money;\n\nclass Formatter')
replace('src/Core/Formatter.php', '    public static function priceInput(float|int|string|null $amount): string\n', "    public static function moneyCents(int $cents): string\n    {\n        $decimal = Money::toDecimalString($cents);\n        $negative = str_starts_with($decimal, '-');\n        $unsigned = ltrim($decimal, '-');\n        [$whole, $fraction] = explode('.', $unsigned, 2);\n        $formatted = number_format((int) $whole, 0, ',', ' ') . ',' . $fraction . ' €';\n        return $negative ? '-' . $formatted : $formatted;\n    }\n\n    public static function priceInput(float|int|string|null $amount): string\n")
replace('src/helpers.php', 'function formatPriceInput(float|int|string|null $amount): string\n', "function formatMoneyCents(int|string|null $cents): string\n{\n    return Formatter::moneyCents((int) ($cents ?? 0));\n}\nfunction formatPriceInput(float|int|string|null $amount): string\n")

# Transactional persistence names are canonical cents/basis points. Avoid doubling existing suffixes.
renames = [
    ('prix_par_personne_snapshot', 'prix_par_personne_snapshot_cents'),
    ('taux_reduction_snapshot', 'taux_reduction_basis_points'),
    ('taux_tva_snapshot', 'taux_tva_basis_points'),
    ('remise_appliquee', 'remise_appliquee_cents'),
    ('prix_total_ligne', 'prix_total_ligne_cents'),
    ('prix_livraison', 'prix_livraison_cents'),
    ('prix_menu', 'prix_menu_cents'),
    ('prix_total', 'prix_total_cents'),
]
for path in Path('src').rglob('*.php'):
    text = path.read_text()
    updated = text
    for old, new in renames:
        updated = re.sub(rf'\b{re.escape(old)}\b', new, updated)
    if path.name in {'PaiementModel.php', 'PaymentLedgerService.php'}:
        updated = re.sub(r'\bmontant\b', 'montant_cents', updated)
    if updated != text:
        path.write_text(updated)

# Order creation carries canonical amount + ISO-4217 snapshot.
p = Path('src/Controllers/CommandeController.php')
text = p.read_text()
text = text.replace("            'prix_total_cents'            => $pricing['total_ttc'],\n            'prix_livraison_cents'        => $pricing['prix_livraison_cents'],", "            'prix_total_cents'            => $pricing['total_ttc_cents'],\n            'currency'                    => $pricing['currency'],\n            'prix_livraison_cents'        => $pricing['prix_livraison_cents'],")
p.write_text(text)

# CommandeModel writes only canonical columns; no decimal mirror.
p = Path('src/Models/CommandeModel.php')
text = p.read_text()
text = text.replace('code_postal_livraison, prix_total_cents, instructions)', 'code_postal_livraison, prix_total_cents, currency, instructions)')
text = text.replace('VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 1)
needle = "                $commandeData['prix_total_cents'],\n                $commandeData['instructions'] ?? null,"
text = text.replace(needle, "                (int) $commandeData['prix_total_cents'],\n                (string) $commandeData['currency'],\n                $commandeData['instructions'] ?? null,")
# Remove float casts on persisted cents/rates.
for key in ['prix_menu_cents','prix_livraison_cents','prix_total_ligne_cents','prix_par_personne_snapshot_cents','taux_tva_basis_points','taux_reduction_basis_points','remise_appliquee_cents']:
    text = text.replace(f"(float)$ligne['{key}']", f"(int)$ligne['{key}']")
text = text.replace("(float)($ligne['prix_par_personne_snapshot_cents'] ?? 0)", "(int)($ligne['prix_par_personne_snapshot_cents'] ?? 0)")
text = text.replace("(float)($ligne['taux_tva_basis_points']          ?? 10.0)", "(int)($ligne['taux_tva_basis_points'] ?? 0)")
text = text.replace("(float)($ligne['taux_reduction_basis_points']    ?? 0)", "(int)($ligne['taux_reduction_basis_points'] ?? 0)")
text = text.replace("(float)($ligne['remise_appliquee_cents']           ?? 0)", "(int)($ligne['remise_appliquee_cents'] ?? 0)")
p.write_text(text)

# Pricing service: cents-only result, integer discount rate, canonical market currency.
p = Path('src/Services/PricingService.php')
text = p.read_text()
text = text.replace('use App\\Config\\SiteConfig;\n', '')
text = text.replace("        $seuilReduction = SiteConfig::discountThreshold();\n        $tauxReduction = SiteConfig::discountRate();", "        $seuilReduction = Configuration::get('discount.threshold');\n        $tauxReduction = Configuration::get('discount.rate_percent');\n        if (!is_string($seuilReduction) || !is_int($tauxReduction)) {\n            throw new RuntimeException('configuration_incomplete:pricing');\n        }\n        $discountThresholdCents = Money::fromDecimal($seuilReduction);\n        $discountRateBasisPoints = $tauxReduction * 100;")
text = text.replace('            Money::fromDecimal($seuilReduction),\n            $tauxReduction', '            $discountThresholdCents,\n            $discountRateBasisPoints')
# Remove decimal presentation keys from pricing lines/total contract.
for line in [
"                'prix_par_personne_snapshot_cents' => Money::toDecimal($line['prix_par_personne_cents']),\n",
"                'prix_menu_brut' => Money::toDecimal($line['prix_menu_brut_cents']),\n",
"                'prix_menu_cents' => Money::toDecimal($line['prix_menu_net_cents']),\n",
"                'remise_appliquee_cents' => Money::toDecimal($line['remise_appliquee_cents']),\n",
"                'prix_livraison_cents' => Money::toDecimal($prixLivraisonCents),\n",
"                'prix_total_ligne_cents' => Money::toDecimal($prixTotalLigneCents),\n",
"            'total_brut' => Money::toDecimal($pricing['total_brut_cents']),\n",
"            'remise_globale' => Money::toDecimal($pricing['remise_globale_cents']),\n",
"            'total_menus_net' => Money::toDecimal($pricing['total_menus_net_cents']),\n",
"            'prix_livraison_cents' => Money::toDecimal($pricing['prix_livraison_cents']),\n",
"            'total_ttc' => Money::toDecimal($pricing['total_ttc_cents']),\n",
"                'seuil_reduction' => $seuilReduction,\n",
]:
    text = text.replace(line, '')
text = text.replace("                'taux_reduction_basis_points' => $pricing['taux_reduction_applique'],", "                'taux_reduction_basis_points' => $pricing['taux_reduction_basis_points'],")
text = text.replace("            'currency' => 'eur',", "            'currency' => (string) Configuration::get('market.currency'),")
text = text.replace("                'seuil_reduction_cents' => Money::fromDecimal($seuilReduction),", "                'seuil_reduction_cents' => $discountThresholdCents,")
text = text.replace("                'taux_reduction' => $pricing['taux_reduction_applique'],", "                'taux_reduction_basis_points' => $pricing['taux_reduction_basis_points'],")
# Exact catalog price change detection.
text = text.replace("                'prix_par_personne' => (float) $row['prix_par_personne'],", "                'prix_par_personne' => (string) $row['prix_par_personne'],")
text = text.replace("            $prixSession = (float) $item['prix_par_personne'];\n            $prixActuel = $prixActuels[$menuId]['prix_par_personne'];\n            if (abs($prixSession - $prixActuel) > 0.001) {", "            $prixSession = (string) $item['prix_par_personne'];\n            $prixActuel = (string) $prixActuels[$menuId]['prix_par_personne'];\n            if (Money::fromDecimal($prixSession) !== Money::fromDecimal($prixActuel)) {")
p.write_text(text)

# Payment ledger persists cents directly.
p = Path('src/Services/PaymentLedgerService.php')
text = p.read_text()
text = text.replace("(commande_id, document_id, type_paiement, nature, montant_cents, mode, date_paiement, reference, note, cree_par)", "(commande_id, document_id, type_paiement, nature, montant_cents, mode, date_paiement, reference, note, cree_par)")
text = text.replace('            Money::toDecimal($amountCents),', '            $amountCents,')
text = text.replace("Money::fromDecimal((string) $commande['prix_total_cents'])", "(int) $commande['prix_total_cents']")
text = text.replace("Money::fromDecimal((string) $stmt->fetchColumn())", "(int) $stmt->fetchColumn()")
text = text.replace('Money::fromDecimal((string) $payment[\'montant_cents\'])', "(int) $payment['montant_cents']")
p.write_text(text)

# Formatter calls on known cents fields must be explicit.
for path in Path('src/Views').rglob('*.php'):
    text = path.read_text()
    updated = re.sub(r'formatPrice\(([^\n;]*\[[\'\"](?:prix_total_cents|prix_menu_cents|prix_livraison_cents|prix_total_ligne_cents|remise_appliquee_cents|montant_cents|total_encaisse_cents|solde_restant_cents)[\'\"][^\n;]*)\)', r'formatMoneyCents(\1)', text)
    if updated != text:
        path.write_text(updated)

print('canonical money refactor applied')
