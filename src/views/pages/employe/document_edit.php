<?php
// src/views/pages/employe/document_edit.php
$typeLabel = ($document['type_document'] ?? '') === 'ticket' ? 'ticket de caisse' : 'facture';
$previewUrl = '/employe/document/apercu?id=' . (int)$document['document_id'];
$isFinalise = ($document['statut'] ?? '') === 'finalise';
$documentRef = $document['numero_document'] ?: ('Brouillon #' . (int)$document['document_id']);
?>
<div class="container py-5 facturation-page">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-receipt', 'title' => 'Éditeur de ' . $typeLabel]); ?>

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
                <div class="facturation-grid-wide">
                    <label for="client-adresse" class="form-label form-label-sm">Adresse</label>
                    <input type="text" class="form-control form-control-sm" id="client-adresse" name="client_adresse" value="<?= sanitize($document['client_adresse'] ?? '') ?>">
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
                $editableLines = $document['lignes'] ?? [];
                $editableLines[] = ['designation' => '', 'quantite' => 1, 'prix_unitaire_ttc' => '', 'taux_tva' => '10'];
                $editableLines[] = ['designation' => '', 'quantite' => 1, 'prix_unitaire_ttc' => '', 'taux_tva' => '10'];
                ?>
                <?php foreach ($editableLines as $index => $ligne): ?>
                    <?php $prixUnitaireTtc = $ligne['prix_unitaire_ttc'] ?? ''; ?>
                    <div class="facturation-line">
                        <input type="text" class="form-control form-control-sm" name="designation[]" value="<?= sanitize($ligne['designation'] ?? '') ?>" placeholder="Désignation">
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="quantite[]" value="<?= sanitize(formatPriceInput($ligne['quantite'] ?? 1)) ?>" aria-label="Quantité ligne <?= $index + 1 ?>">
                        <input type="number" step="0.01" class="form-control form-control-sm" name="prix_unitaire_ttc[]" value="<?= sanitize($prixUnitaireTtc === '' ? '' : formatPriceInput($prixUnitaireTtc)) ?>" aria-label="Prix unitaire TTC ligne <?= $index + 1 ?>">
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="taux_tva[]" value="<?= sanitize(formatPriceInput($ligne['taux_tva'] ?? 10)) ?>" aria-label="Taux TVA ligne <?= $index + 1 ?>">
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
