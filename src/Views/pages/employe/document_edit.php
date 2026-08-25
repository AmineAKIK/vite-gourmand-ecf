<?php
$typeLabel = match ($document['type_document'] ?? '') {
    'ticket' => 'ticket de caisse',
    'devis' => 'devis',
    'acompte' => "facture d'acompte",
    default => 'facture',
};
$previewUrl = '/employe/document/apercu?id=' . (int) $document['document_id'];
$isFinalise = ($document['statut'] ?? '') === 'finalise';
$documentRef = $document['numero_document'] ?: ('Brouillon #' . (int) $document['document_id']);
?>
<div class="container py-5">
    <?php partial('partials/page_title_bar', ['icon' => 'bi-receipt', 'title' => 'Éditeur de ' . $typeLabel]); ?>
    <?php if (!empty($siretMissing) && !$isFinalise): ?>
        <div class="alert alert-warning"><strong>SIRET manquant.</strong> La finalisation reste bloquée tant que les informations légales requises ne sont pas configurées. <a href="/admin/parametres" class="alert-link">Configurer</a></div>
    <?php endif; ?>
    <div class="toolbar mb-4"><a href="/employe/commandes" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Commandes</a><a href="<?= sanitize($previewUrl) ?>" class="btn btn-brand btn-sm"><i class="bi bi-eye me-1"></i>Aperçu imprimable</a></div>

    <form method="POST" action="/employe/document/modifier">
        <?= csrfField() ?><input type="hidden" name="document_id" value="<?= (int) $document['document_id'] ?>">
        <fieldset <?= $isFinalise ? 'disabled' : '' ?>>
            <section class="card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h2 class="h5 mb-1">Informations</h2><p class="text-muted small mb-0"><?= sanitize($documentRef) ?> · commande <?= sanitize($commande['numero_commande'] ?? '') ?></p></div><span class="badge <?= $isFinalise ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $isFinalise ? 'Finalisé' : 'Brouillon' ?></span></div>
                <div class="row g-3">
                    <div class="col-md-6"><label for="date-emission" class="form-label">Date d'émission</label><input type="date" class="form-control" id="date-emission" name="date_emission" value="<?= sanitize($document['date_emission'] ?? date('Y-m-d')) ?>"></div>
                    <div class="col-md-6"><label for="date-prestation" class="form-label">Date de prestation</label><input type="date" class="form-control" id="date-prestation" name="date_prestation" value="<?= sanitize($document['date_prestation'] ?? '') ?>"></div>
                    <div class="col-md-6"><label for="client-nom" class="form-label">Client</label><input type="text" class="form-control" id="client-nom" name="client_nom" value="<?= sanitize($document['client_nom'] ?? '') ?>"></div>
                    <div class="col-md-6"><label for="client-email" class="form-label">Email</label><input type="email" class="form-control" id="client-email" name="client_email" value="<?= sanitize($document['client_email'] ?? '') ?>"></div>
                    <div class="col-md-6"><label for="client-telephone" class="form-label">Téléphone</label><input type="text" class="form-control" id="client-telephone" name="client_telephone" value="<?= sanitize($document['client_telephone'] ?? '') ?>"></div>
                    <div class="col-md-6"><label for="client-siren" class="form-label">SIREN client</label><input type="text" class="form-control" id="client-siren" name="client_siren" value="<?= sanitize($document['client_siren'] ?? '') ?>" maxlength="9" inputmode="numeric"></div>
                    <div class="col-12"><label for="client-adresse" class="form-label">Adresse client</label><input type="text" class="form-control" id="client-adresse" name="client_adresse" value="<?= sanitize($document['client_adresse'] ?? '') ?>"></div>
                    <div class="col-md-4"><label for="client-code-postal" class="form-label">Code postal</label><input type="text" class="form-control" id="client-code-postal" name="client_code_postal" value="<?= sanitize($document['client_code_postal'] ?? '') ?>"></div>
                    <div class="col-md-8"><label for="client-ville" class="form-label">Ville</label><input type="text" class="form-control" id="client-ville" name="client_ville" value="<?= sanitize($document['client_ville'] ?? '') ?>"></div>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <h2 class="h5 mb-3">Livraison et opération</h2>
                <div class="row g-3">
                    <div class="col-12"><label for="adresse-livraison" class="form-label">Adresse de livraison</label><input type="text" class="form-control" id="adresse-livraison" name="adresse_livraison" value="<?= sanitize($document['adresse_livraison'] ?? '') ?>"></div>
                    <div class="col-md-4"><label for="code-postal-livraison" class="form-label">CP livraison</label><input type="text" class="form-control" id="code-postal-livraison" name="code_postal_livraison" value="<?= sanitize($document['code_postal_livraison'] ?? '') ?>"></div>
                    <div class="col-md-4"><label for="ville-livraison" class="form-label">Ville livraison</label><input type="text" class="form-control" id="ville-livraison" name="ville_livraison" value="<?= sanitize($document['ville_livraison'] ?? '') ?>"></div>
                    <div class="col-md-4"><label for="categorie-operation" class="form-label">Catégorie d'opération</label><select class="form-select" id="categorie-operation" name="categorie_operation"><option value="mixte" <?= ($document['categorie_operation'] ?? 'mixte') === 'mixte' ? 'selected' : '' ?>>Biens + services</option><option value="biens" <?= ($document['categorie_operation'] ?? '') === 'biens' ? 'selected' : '' ?>>Livraison de biens</option><option value="services" <?= ($document['categorie_operation'] ?? '') === 'services' ? 'selected' : '' ?>>Prestation de services</option></select></div>
                    <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="option-tva-debits" name="option_tva_debits" value="1" <?= !empty($document['option_tva_debits']) ? 'checked' : '' ?>><label class="form-check-label" for="option-tva-debits">Option TVA sur les débits</label></div>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <h2 class="h5 mb-3">Lignes</h2>
                <?php
                $editableLines = $document['lignes'] ?? [];
                $defaultRate = !empty($tauxTvaOptions) ? (float) $tauxTvaOptions[0]['taux'] : null;
                $editableLines[] = ['designation' => '', 'quantite' => 1, 'prix_unitaire_ttc' => '', 'taux_tva' => $defaultRate];
                $editableLines[] = ['designation' => '', 'quantite' => 1, 'prix_unitaire_ttc' => '', 'taux_tva' => $defaultRate];
                ?>
                <div class="d-grid gap-2">
                    <?php foreach ($editableLines as $index => $ligne): ?>
                    <?php $lineRate = $ligne['taux_tva'] === null ? null : (float) $ligne['taux_tva']; ?>
                    <div class="row g-2 align-items-end border-bottom border-subtle pb-2">
                        <div class="col-md-6"><label class="form-label small" for="ligne-designation-<?= $index ?>">Désignation</label><input type="text" class="form-control form-control-sm" id="ligne-designation-<?= $index ?>" name="designation[]" value="<?= sanitize($ligne['designation'] ?? '') ?>"></div>
                        <div class="col-md-2"><label class="form-label small" for="ligne-quantite-<?= $index ?>">Quantité</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ligne-quantite-<?= $index ?>" name="quantite[]" value="<?= sanitize(formatPriceInput($ligne['quantite'] ?? 1)) ?>"></div>
                        <div class="col-md-2"><label class="form-label small" for="ligne-prix-<?= $index ?>">PU TTC</label><input type="number" step="0.01" class="form-control form-control-sm" id="ligne-prix-<?= $index ?>" name="prix_unitaire_ttc[]" value="<?= ($ligne['prix_unitaire_ttc'] ?? '') === '' ? '' : sanitize(formatPriceInput($ligne['prix_unitaire_ttc'])) ?>"></div>
                        <div class="col-md-2"><label class="form-label small" for="ligne-tva-<?= $index ?>">TVA %</label><select class="form-select form-select-sm" id="ligne-tva-<?= $index ?>" name="taux_tva[]"><?php foreach ($tauxTvaOptions as $opt): ?><option value="<?= (float) $opt['taux'] ?>" <?= $lineRate !== null && (float) $opt['taux'] === $lineRate ? 'selected' : '' ?>><?= sanitize($opt['libelle']) ?> (<?= number_format((float) $opt['taux'], 2) ?>%)</option><?php endforeach; ?><?php $rates = array_map(fn($opt) => (float) $opt['taux'], $tauxTvaOptions); if ($lineRate !== null && !in_array($lineRate, $rates, true) && ($ligne['prix_unitaire_ttc'] ?? '') !== ''): ?><option value="<?= $lineRate ?>" selected><?= number_format($lineRate, 2) ?>% (taux archivé)</option><?php endif; ?></select></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <div class="row g-3">
                    <div class="col-12"><label for="note-publique" class="form-label">Note visible client</label><textarea class="form-control" id="note-publique" name="note_publique" rows="2"><?= sanitize($document['note_publique'] ?? '') ?></textarea></div>
                    <div class="col-12"><label for="mention-legale" class="form-label">Mention / pied de document</label><textarea class="form-control" id="mention-legale" name="mention_legale" rows="2"><?= sanitize($document['mention_legale'] ?? '') ?></textarea></div>
                    <?php if (($document['type_document'] ?? '') === 'facture'): ?><div class="col-md-6"><label for="montant-acompte-verse" class="form-label">Acompte déjà versé (€)</label><input type="number" step="0.01" min="0" class="form-control" id="montant-acompte-verse" name="montant_acompte_verse" value="<?= sanitize(number_format((float) ($document['montant_acompte_verse'] ?? 0), 2, '.', '')) ?>"></div><?php endif; ?>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4"><div><span class="me-3">HT <?= sanitize(formatPrice($document['total_ht'] ?? 0)) ?></span><span class="me-3">TVA <?= sanitize(formatPrice($document['total_tva'] ?? 0)) ?></span><strong>TTC <?= sanitize(formatPrice($document['total_ttc'] ?? 0)) ?></strong></div><?php if ($isFinalise): ?><span class="text-muted"><i class="bi bi-lock me-1"></i>Document finalisé et verrouillé.</span><?php else: ?><div class="d-flex gap-2"><button type="submit" class="btn btn-outline-secondary"><i class="bi bi-save me-1"></i>Enregistrer</button><button type="submit" class="btn btn-brand" formaction="/employe/document/finaliser" data-confirm="Finaliser ce document ? Il recevra un numéro définitif et ne sera plus modifiable."><i class="bi bi-lock me-1"></i>Finaliser</button></div><?php endif; ?></div>
            </section>
        </fieldset>
    </form>
</div>
