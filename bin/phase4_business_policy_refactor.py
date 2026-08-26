from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f'missing marker in {path}: {old[:80]!r}')
    p.write_text(text.replace(old, new, 1))


replace('src/Config/ConfigurationRegistry.php',
"            self::tenant('order.number_prefix', 'commande_prefixe', ConfigurationType::STRING, true, $admin, 'orders', 'Préfixe public des références de commande.', null, ['max_length' => 12, 'pattern' => '/^[A-Za-z0-9]+$/']),\n",
"            self::tenant('order.number_prefix', 'commande_prefixe', ConfigurationType::STRING, true, $admin, 'orders', 'Préfixe public des références de commande.', null, ['max_length' => 12, 'pattern' => '/^[A-Za-z0-9]+$/']),\n            self::tenant('order.minimum_lead_hours', 'commande_delai_min_heures', ConfigurationType::INTEGER, true, $admin, 'orders', 'Délai minimum entre commande et prestation, en heures.', null, ['min' => 1, 'max' => 8760]),\n            self::tenant('order.maximum_advance_days', 'commande_horizon_max_jours', ConfigurationType::INTEGER, true, $admin, 'orders', 'Horizon maximal de réservation, en jours.', null, ['min' => 1, 'max' => 1095]),\n            self::tenant('order.cancellation_cutoff_hours', 'commande_annulation_limite_heures', ConfigurationType::INTEGER, true, $admin, 'orders', 'Délai limite d’annulation client avant prestation, en heures.', null, ['min' => 0, 'max' => 8760]),\n            self::tenant('quote.validity_days', 'devis_validite_jours', ConfigurationType::INTEGER, true, $admin, 'quote', 'Durée de validité des devis, en jours.', null, ['min' => 1, 'max' => 365]),\n            self::tenant('material.return_days', 'materiel_retour_jours', ConfigurationType::INTEGER, true, $admin, 'material', 'Délai de retour du matériel, en jours.', null, ['min' => 0, 'max' => 365]),\n            self::tenant('material.late_fee_cents', 'materiel_penalite_retard_centimes', ConfigurationType::INTEGER, true, $admin, 'material', 'Pénalité de retard matériel en centimes.', null, ['min' => 0, 'max' => 10000000]),\n            self::tenant('reminder.order_days_before', 'rappels_commande_jours_avant', ConfigurationType::STRING_LIST, true, $admin, 'notifications', 'Jours avant prestation auxquels envoyer les rappels.', null, ['max_items' => 20]),\n")

replace('src/Config/ConfigurationCompleteness.php',
"            'order.number_prefix',\n            'discount.threshold',",
"            'order.number_prefix',\n            'order.minimum_lead_hours',\n            'order.maximum_advance_days',\n            'order.cancellation_cutoff_hours',\n            'quote.validity_days',\n            'material.return_days',\n            'material.late_fee_cents',\n            'reminder.order_days_before',\n            'discount.threshold',")
replace('src/Config/ConfigurationCompleteness.php',
"            'order.number_prefix',\n            'discount.threshold',",
"            'order.number_prefix',\n            'order.minimum_lead_hours',\n            'order.maximum_advance_days',\n            'order.cancellation_cutoff_hours',\n            'quote.validity_days',\n            'material.return_days',\n            'material.late_fee_cents',\n            'reminder.order_days_before',\n            'discount.threshold',")

p = Path('src/Services/CommandeService.php')
text = p.read_text()
text = text.replace('use App\\Domain\\InputPolicy;\n', 'use App\\Config\\Configuration;\nuse App\\Domain\\BusinessPolicy;\nuse App\\Domain\\InputPolicy;\n')
old = "        $datePrestation = DateTimeImmutable::createFromFormat('!Y-m-d', $payload['date_prestation']);\n        $tomorrow       = new DateTimeImmutable('tomorrow');\n        $maxDate        = new DateTimeImmutable('+365 days');\n\n        if (!$datePrestation || $datePrestation < $tomorrow) {\n            throw new InvalidArgumentException('La date de prestation doit être au minimum demain.');\n        }\n        if ($datePrestation > $maxDate) {\n            throw new InvalidArgumentException('La date de prestation ne peut pas dépasser 1 an à l\\'avance.');\n        }\n"
new = "        $datePrestation = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $payload['date_prestation'] . ' ' . $payload['heure_livraison']);\n        if (!$datePrestation) {\n            throw new InvalidArgumentException('Date ou heure de prestation invalide.');\n        }\n\n        $policy = new BusinessPolicy(static fn(string $key): mixed => Configuration::get($key));\n        $policy->assertOrderSchedule($datePrestation, new DateTimeImmutable());\n"
if old not in text:
    raise SystemExit('CommandeService date policy marker missing')
p.write_text(text.replace(old, new, 1))

replace('src/Controllers/Admin/ParametresController.php',
"        'commandes_max_par_jour',\n        'reduction_seuil',",
"        'commandes_max_par_jour',\n        'commande_delai_min_heures',\n        'commande_horizon_max_jours',\n        'commande_annulation_limite_heures',\n        'devis_validite_jours',\n        'materiel_retour_jours',\n        'materiel_penalite_retard_centimes',\n        'rappels_commande_jours_avant',\n        'reduction_seuil',")

replace('src/Views/pages/admin/parametres.php',
"                        <div class=\"col-12 col-lg-8\">\n                            <label class=\"form-label fw-medium\" for=\"cron_secret_token\">Token secret cron</label>\n                            <div class=\"input-group\">\n                                <input type=\"text\" class=\"form-control font-monospace\" id=\"cron_secret_token\"\n                                       name=\"cron_secret_token\"\n                                       value=\"<?= htmlspecialchars($config['cron_secret_token'] ?? '') ?>\"\n                                       maxlength=\"128\" placeholder=\"Laisser vide pour désactiver\">\n                                <button type=\"button\" class=\"btn btn-outline-secondary\" id=\"btn-gen-token\"\n                                        title=\"Générer un token aléatoire\">\n                                    <i class=\"bi bi-arrow-repeat\"></i>\n                                </button>\n                            </div>\n                            <div class=\"form-text\">Chaîne aléatoire longue — utilisée comme clé secrète dans l'URL cron.</div>\n                        </div>\n",
"                        <div class=\"col-12 col-lg-8\">\n                            <div class=\"alert alert-secondary mb-0\">Le secret cron est une configuration opérateur et n’est jamais stocké dans les paramètres tenant.</div>\n                        </div>\n")

p = Path('src/Views/pages/admin/parametres.php')
text = p.read_text()
start = text.find('<script nonce="<?= cspNonce() ?>">')
end = text.find('</script>', start)
if start >= 0 and end >= 0 and 'btn-gen-token' in text[start:end]:
    text = text[:start] + text[end + len('</script>'):]
p.write_text(text)

print('Phase 4 business policy refactor applied')
