from pathlib import Path

p = Path('src/Services/StripeWebhookFulfillmentService.php')
text = p.read_text()

old_order = '''        $stmt = $db->prepare(
            'INSERT INTO commande (
                numero_commande, utilisateur_id, date_prestation, heure_livraison,
                adresse_livraison, ville_livraison, code_postal_livraison, prix_total_cents, instructions
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $commandeData['numero_commande'],
            $commandeData['utilisateur_id'],
            $commandeData['date_prestation'],
            $commandeData['heure_livraison'],
            $commandeData['adresse_livraison'],
            $commandeData['ville_livraison'],
            $commandeData['code_postal_livraison'],
            $commandeData['prix_total_cents'],
            $commandeData['instructions'] ?? null,
        ]);
'''
new_order = '''        $stmt = $db->prepare(
            'INSERT INTO commande (
                numero_commande, utilisateur_id, date_prestation, heure_livraison,
                adresse_livraison, ville_livraison, code_postal_livraison,
                prix_total_cents, currency, instructions
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $commandeData['numero_commande'],
            $commandeData['utilisateur_id'],
            $commandeData['date_prestation'],
            $commandeData['heure_livraison'],
            $commandeData['adresse_livraison'],
            $commandeData['ville_livraison'],
            $commandeData['code_postal_livraison'],
            (int) $commandeData['prix_total_cents'],
            (string) $commandeData['currency'],
            $commandeData['instructions'] ?? null,
        ]);
'''
if old_order not in text:
    raise SystemExit('Stripe order insert marker missing')
text = text.replace(old_order, new_order)

old_lines = '''        $ligneStmt = $db->prepare(
            'INSERT INTO commande_ligne (
                commande_id, menu_id, nombre_personne, prix_menu_cents, prix_livraison_cents,
                prix_total_ligne_cents, prix_par_personne_snapshot_cents, taux_tva_basis_points,
                taux_reduction_basis_points, remise_appliquee_cents, taux_tva_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );

        foreach ($lignes as $ligne) {
            $ligneStmt->execute([
                $commandeId,
                (int) $ligne['menu_id'],
                (int) $ligne['nombre_personne'],
                (float) $ligne['prix_menu_cents'],
                (float) $ligne['prix_livraison_cents'],
                (float) $ligne['prix_total_ligne_cents'],
                (float) ($ligne['prix_par_personne_snapshot_cents'] ?? 0),
                (float) ($ligne['taux_tva_basis_points'] ?? 10.0),
                (float) ($ligne['taux_reduction_basis_points'] ?? 0),
                (float) ($ligne['remise_appliquee_cents'] ?? 0),
                isset($ligne['taux_tva_id']) ? (int) $ligne['taux_tva_id'] : null,
            ]);
'''
new_lines = '''        $ligneStmt = $db->prepare(
            'INSERT INTO commande_ligne (
                commande_id, menu_id, nombre_personne, prix_menu_cents, prix_livraison_cents,
                prix_total_ligne_cents, prix_par_personne_snapshot_cents,
                taux_tva_menu_basis_points, taux_tva_livraison_basis_points,
                taux_reduction_basis_points, remise_appliquee_cents,
                taux_tva_menu_id, taux_tva_livraison_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );

        foreach ($lignes as $ligne) {
            $ligneStmt->execute([
                $commandeId,
                (int) $ligne['menu_id'],
                (int) $ligne['nombre_personne'],
                (int) $ligne['prix_menu_cents'],
                (int) $ligne['prix_livraison_cents'],
                (int) $ligne['prix_total_ligne_cents'],
                (int) $ligne['prix_par_personne_snapshot_cents'],
                (int) $ligne['taux_tva_menu_basis_points'],
                (int) $ligne['taux_tva_livraison_basis_points'],
                (int) $ligne['taux_reduction_basis_points'],
                (int) $ligne['remise_appliquee_cents'],
                isset($ligne['taux_tva_menu_id']) ? (int) $ligne['taux_tva_menu_id'] : null,
                isset($ligne['taux_tva_livraison_id']) ? (int) $ligne['taux_tva_livraison_id'] : null,
            ]);
'''
if old_lines not in text:
    raise SystemExit('Stripe line insert marker missing')
text = text.replace(old_lines, new_lines)
p.write_text(text)

print('Stripe canonical money fulfillment finalized')
