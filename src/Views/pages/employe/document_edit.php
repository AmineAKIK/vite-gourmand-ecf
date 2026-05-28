<?php
// src/views/pages/employe/document_edit.php
$typeLabel = match ($document['type_document'] ?? '') {
    'ticket'  => 'ticket de caisse',
    'devis'   => 'devis',
    'acompte' => "facture d'acompte",
    default   => 'facture',
};
$previewUrl = '/employe/document/apercu?id=' . (int)$document['document_id'];
$isFinalise = ($document['statut'] ?? '') === 'finalise';
$documentRef = $document['numero_document'] ?: ('Brouillon #' . (int)$document['document_id']);
?>
<div class="container py-5 facturation-page">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-receipt', 'title' => 'Éditeur de ' . $typeLabel]); ?>

    <?php if (!empty($siretMissing) && !$isFinalise): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong>SIRET manquant.</strong>
            Les documents peuvent être édités en brouillon, mais la finalisation sera bloquée tant que le numéro SIRET de l'entreprise n'est pas renseigné.
            <a href="/admin/parametres" class="alert-link ms-1">Configurer les paramètres entreprise →</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="facturation-toolbar mb-4">
        <a href="/employe/commandes" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Commandes
        </a>
        <a href="<?= sanitize($previewUrl) ?>" class="btn btn-vg btn-sm">
            <i class="bi bi-eye me-1"></i>Aperçu imprimable
        </a>
    </div>

    <form method="POST" action="/employe/document/modifier" class="facturation-editor">
        <?= csrfField() ?>
        <input type="hidden" name="document_id" value="<?= (int)$document['document_id'] ?>">
        <fieldset class="facturation-fieldset" <?= $isFinalise ? 'disabled' : '' ?>>

        <section class="facturation-panel">
            <div class="facturation-panel-header">
                <div>
                    <h2>Informations</h2>
                    <p><?= sanitize($documentRef) ?> lié à la commande <?= sanitize($commande['numero_commande'] ?? '') ?>.</p>
                </div>
                <span class="badge-statut <?= $isFinalise ? 'statut-accepte' : 'statut-en_attente' ?>"><?= $isFinalise ? 'Finalisé' : 'Brouillon' ?></span>
            </div>

            <div class="facturation-grid">
                <div>
                    <label for="date-emission" class="form-label form-label-sm">Date d'émission</label>
                    <input type="date" class="form-control form-control-sm" id="date-emission" name="date_emission" value="<?= sanitize($document['date_emission'] ?? date('Y-m-d')) ?>">
                </div>
                <div>
                    <label for="date-prestation" class="form-label form-label-sm">Date de prestation</label>
                    <input type="date" class="form-control form-control-sm" id="date-prestation" name="date_prestation" value="<?= sanitize($document['date_prestation'] ?? '') ?>">
                </div>
                <div>
                    <label for="client-nom" class="form-label form-label-sm">Client</label>
                    <input type="text" class="form-control form-control-sm" id="client-nom" name="client_nom" value="<?= sanitize($document['client_nom'] ?? '') ?>">
                </div>
                <div>
                    <label for="client-email" class="form-label form-label-sm">Email</label>
                    <input type="email" class="form-control form-control-sm" id="client-email" name="client_email" value="<?= sanitize($document['client_email'] ?? '') ?>">
                </div>
                <div>
                    <label for="client-telephone" class="form-label form-label-sm">Téléphone</label>
                    <input type="text" class="form-control form-control-sm" id="client-telephone" name="client_telephone" value="<?= sanitize($document['client_telephone'] ?? '') ?>">
                </div>
                <div>
                    <label for="client-ville" class="form-label form-label-sm">Ville</label>
                    <input type="text" class="form-control form-control-sm" id="client-ville" name="client_ville" value="<?= sanitize($document['client_ville'] ?? '') ?>">
                </div>
                <div>
                    <label for="client-code-postal" class="form-label form-label-sm">Code postal</label>
                    <input type="text" class="form-control form-control-sm" id="client-code-postal" name="client_code_postal" value="<?= sanitize($document['client_code_postal'] ?? '') ?>">
                </div>
                <div>
                    <label for="client-siren" class="form-label form-label-sm">SIREN client</label>
                    <input type="text" class="form-control form-control-sm" id="client-siren" name="client_siren" value="<?= sanitize($document['client_siren'] ?? '') ?>" maxlength="9" inputmode="numeric">
                </div>
                <div class="facturation-grid-wide">
                    <label for="client-adresse" class="form-label form-label-sm">Adresse</label>
                    <input type="text" class="form-control form-control-sm" id="client-adresse" name="client_adresse" value="<?= sanitize($document['client_adresse'] ?? '') ?>">
                </div>
            </div>
        </section>

        <section class="facturation-panel">
            <div class="facturation-panel-header">
                <div>
                    <h2>Facturation électronique</h2>
                    <p>Champs préparatoires pour les futures plateformes de facturation.</p>
                </div>
            </div>

            <div class="facturation-grid">
                <div class="facturation-grid-wide">
                    <label for="adresse-livraison" class="form-label form-label-sm">Adresse de livraison</label>
                    <input type="text" class="form-control form-control-sm" id="adresse-livraison" name="adresse_livraison" value="<?= sanitize($document['adresse_livraison'] ?? '') ?>">
                </div>
                <div>
                    <label for="code-postal-livraison" class="form-label form-label-sm">CP livraison</label>
                    <input type="text" class="form-control form-control-sm" id="code-postal-livraison" name="code_postal_livraison" value="<?= sanitize($document['code_postal_livraison'] ?? '') ?>">
                </div>
                <div>
                    <label for="ville-livraison" class="form-label form-label-sm">Ville livraison</label>
                    <input type="text" class="form-control form-control-sm" id="ville-livraison" name="ville_livraison" value="<?= sanitize($document['ville_livraison'] ?? '') ?>">
                </div>
                <div>
                    <label for="categorie-operation" class="form-label form-label-sm">Catégorie d'opération</label>
                    <select class="form-select form-select-sm" id="categorie-operation" name="categorie_operation">
                        <option value="mixte" <?= ($document['categorie_operation'] ?? 'mixte') === 'mixte' ? 'selected' : '' ?>>Biens + services</option>
                        <option value="biens" <?= ($document['categorie_operation'] ?? '') === 'biens' ? 'selected' : '' ?>>Livraison de biens</option>
                        <option value="services" <?= ($document['categorie_operation'] ?? '') === 'services' ? 'selected' : '' ?>>Prestation de services</option>
                    </select>
                </div>
                <div class="facturation-check">
                    <input class="form-check-input" type="checkbox" id="option-tva-debits" name="option_tva_debits" value="1" <?= !empty($document['option_tva_debits']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="option-tva-debits">Option TVA sur les débits</label>
                </div>
            </div>
        </section>

        <section class="facturation-panel">
            <div class="facturation-panel-header">
                <div>
                    <h2>Lignes</h2>
                    <p>Les prix unitaires sont saisis en TTC pour rester cohérents avec les commandes existantes.</p>
                </div>
            </div>

            <div class="facturation-lines">
                <div class="facturation-line facturation-line-head" aria-hidden="true">
                    <span>Désignation</span>
                    <span>Qté</span>
                    <span>PU TTC</span>
                    <span>TVA %</span>
                </div>
                <?php
                $editableLines   = $document['lignes'] ?? [];
                $defaultTvaBlank = !empty($tauxTvaOptions) ? (float)$tauxTvaOptions[0]['taux'] : 10.0;
                $editableLines[] = ['designation' => '', 'quantite' => 1, 'prix_unitaire_ttc' => '', 'taux_tva' => $defaultTvaBlank];
                $editableLines[] = ['designation' => '', 'quantite' => 1, 'prix_unitaire_ttc' => '', 'taux_tva' => $defaultTvaBlank];
                ?>
                <?php foreach ($editableLines as $index => $ligne): ?>
                    <?php $prixUnitaireTtc = $ligne['prix_unitaire_ttc'] ?? ''; ?>
                    <div class="facturation-line">
                        <div class="facturation-line-field facturation-line-designation">
                            <label class="facturation-line-label" for="ligne-designation-<?= $index ?>">Désignation</label>
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                id="ligne-designation-<?= $index ?>"
                                name="designation[]"
                                value="<?= sanitize($ligne['designation'] ?? '') ?>"
                                placeholder="Désignation"
                            >
                        </div>
                        <div class="facturation-line-field">
                            <label class="facturation-line-label" for="ligne-quantite-<?= $index ?>">Quantité</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control form-control-sm"
                                id="ligne-quantite-<?= $index ?>"
                                name="quantite[]"
                                value="<?= sanitize(formatPriceInput($ligne['quantite'] ?? 1)) ?>"
                            >
                        </div>
                        <div class="facturation-line-field">
                            <label class="facturation-line-label" for="ligne-prix-<?= $index ?>">PU TTC</label>
                            <input
                                type="number"
                                step="0.01"
                                class="form-control form-control-sm"
                                id="ligne-prix-<?= $index ?>"
                                name="prix_unitaire_ttc[]"
                                value="<?= sanitize($prixUnitaireTtc === '' ? '' : formatPriceInput($prixUnitaireTtc)) ?>"
                            >
                        </div>
                        <div class="facturation-line-field">
                            <label class="facturation-line-label" for="ligne-tva-<?= $index ?>">TVA %</label>
                            <?php
                            $ligneTauxTva = (float)($ligne['taux_tva'] ?? 10);
                            ?>
                            <select
                                class="form-select form-select-sm"
                                id="ligne-tva-<?= $index ?>"
                                name="taux_tva[]"
                            >
                                <?php foreach ($tauxTvaOptions as $opt): ?>
                                <option
                                    value="<?= (float)$opt['taux'] ?>"
                                    <?= (float)$opt['taux'] === $ligneTauxTva ? 'selected' : '' ?>
                                >
                                    <?= sanitize($opt['libelle']) ?> (<?= number_format((float)$opt['taux'], 2) ?>%)
                                </option>
                                <?php endforeach; ?>
                                <?php
                                // If the stored rate isn't in current active list, add it as a fallback option
                                $ratesInList = array_map(fn($o) => (float)$o['taux'], $tauxTvaOptions);
                                if (!in_array($ligneTauxTva, $ratesInList, true) && $prixUnitaireTtc !== ''):
                                ?>
                                <option value="<?= $ligneTauxTva ?>" selected>
                                    <?= number_format($ligneTauxTva, 2) ?>% (taux archivé)
                                </option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="facturation-panel">
            <div class="facturation-grid">
                <div class="facturation-grid-wide">
                    <label for="note-publique" class="form-label form-label-sm">Note visible client</label>
                    <textarea class="form-control form-control-sm" id="note-publique" name="note_publique" rows="2"><?= sanitize($document['note_publique'] ?? '') ?></textarea>
                </div>
                <div class="facturation-grid-wide">
                    <label for="mention-legale" class="form-label form-label-sm">Mention / pied de document</label>
                    <textarea class="form-control form-control-sm" id="mention-legale" name="mention_legale" rows="2"><?= sanitize($document['mention_legale'] ?? '') ?></textarea>
                </div>
                <?php if (($document['type_document'] ?? '') === 'facture'): ?>
                <div>
                    <label for="montant-acompte-verse" class="form-label form-label-sm">Acompte déjà versé (€)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           id="montant-acompte-verse" name="montant_acompte_verse"
                           value="<?= sanitize(number_format((float)($document['montant_acompte_verse'] ?? 0), 2, '.', '')) ?>">
                    <div class="form-text">Pré-rempli si un acompte finalisé existe pour cette commande.</div>
                </div>
                <?php endif ?>
                <?php if (($document['type_document'] ?? '') === 'acompte'): ?>
                <div class="facturation-grid-wide">
                    <div class="alert alert-info mb-0 py-2 px-3" role="alert">
                        <i class="bi bi-info-circle me-1"></i>
                        Ce montant sera automatiquement déduit lors de la création de la facture définitive.
                    </div>
                </div>
                <?php endif ?>
            </div>

            <div class="facturation-submit">
                <div class="facturation-total-preview">
                    <span>Total HT <?= sanitize(formatPrice($document['total_ht'] ?? 0)) ?></span>
                    <span>TVA <?= sanitize(formatPrice($document['total_tva'] ?? 0)) ?></span>
                    <strong>TTC <?= sanitize(formatPrice($document['total_ttc'] ?? 0)) ?></strong>
                </div>
                <?php if ($isFinalise): ?>
                    <p class="facturation-locked-note mb-0">
                        <i class="bi bi-lock me-1"></i>Document finalisé et verrouillé.
                    </p>
                <?php else: ?>
                    <div class="facturation-submit-actions">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-save me-1"></i>Enregistrer
                        </button>
                        <button
                            type="submit"
                            class="btn btn-vg"
                            formaction="/employe/document/finaliser"
                            data-confirm="Finaliser ce document ? Il recevra un numéro définitif et ne sera plus modifiable."
                        >
                            <i class="bi bi-lock me-1"></i>Finaliser
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        </fieldset>
    </form>

</div>
