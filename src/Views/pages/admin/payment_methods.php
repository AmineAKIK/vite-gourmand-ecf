<?php
$pageTitle = buildPageTitle('Moyens de paiement');
?>

<?php partial('partials/page_title_bar', ['icon' => 'bi-credit-card-2-front', 'title' => 'Moyens de paiement']); ?>

<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
    <div>
        <p class="text-muted mb-1">Définissez explicitement les moyens disponibles au checkout et pour les encaissements employés.</p>
        <p class="small text-muted mb-0">Désactiver une méthode n’altère jamais le moyen mémorisé sur les commandes historiques.</p>
    </div>
    <a href="/admin/parametres?tab=paiement" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Conditions de paiement
    </a>
</div>

<form method="POST" action="/admin/parametres/modifier">
    <?= csrfField() ?>
    <input type="hidden" name="_section" value="payment_methods">

    <div class="row g-4">
        <?php foreach ($paymentMethods as $method): ?>
            <?php
                $code = (string)$method['code'];
                $isStripe = ($method['provider'] ?? null) === 'stripe';
                $providerReady = (bool)($method['provider_ready'] ?? true);
                $missingKeys = $method['provider_missing_keys'] ?? [];
            ?>
            <div class="col-12 col-xl-6">
                <section class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="h6 mb-1"><?= sanitize($method['label']) ?></h2>
                            <code><?= sanitize($code) ?></code>
                        </div>
                        <?php if ($method['provider']): ?>
                            <span class="badge <?= $providerReady ? 'text-bg-success' : 'text-bg-warning' ?>">
                                <?= $providerReady ? 'Provider prêt' : 'Provider incomplet' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Hors ligne</span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <?php if ($isStripe && !$providerReady): ?>
                            <div class="alert alert-warning small" role="alert">
                                <strong>Activation checkout impossible tant que la configuration opérateur manque.</strong>
                                <?php if ($missingKeys): ?>
                                    <div class="mt-1">Clés requises : <code><?= sanitize(implode(', ', $missingKeys)) ?></code></div>
                                <?php endif; ?>
                                Aucun secret n’est affiché ou stocké ici.
                            </div>
                        <?php endif; ?>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox"
                                   id="payment-<?= sanitize($code) ?>-active"
                                   name="payment_methods[<?= sanitize($code) ?>][active]"
                                   <?= $method['actif'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="payment-<?= sanitize($code) ?>-active">Méthode active</label>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="payment-<?= sanitize($code) ?>-checkout"
                                           name="payment_methods[<?= sanitize($code) ?>][checkout_enabled]"
                                           <?= $method['checkout_enabled'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="payment-<?= sanitize($code) ?>-checkout">Disponible au checkout</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="payment-<?= sanitize($code) ?>-manual"
                                           name="payment_methods[<?= sanitize($code) ?>][manual_collection_enabled]"
                                           <?= $method['manual_collection_enabled'] ? 'checked' : '' ?>
                                           <?= !$method['supports_manual_collection'] ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="payment-<?= sanitize($code) ?>-manual">Encaissement employé</label>
                                </div>
                                <?php if (!$method['supports_manual_collection']): ?>
                                    <div class="form-text">Interdit par la capacité produit.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <fieldset class="border rounded p-3 mb-3">
                            <legend class="float-none w-auto px-2 fs-6 mb-0">Types autorisés</legend>
                            <?php if ($isStripe): ?>
                                <input type="hidden" name="payment_methods[<?= sanitize($code) ?>][allow_single_payment]" value="1">
                            <?php endif; ?>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="payment-<?= sanitize($code) ?>-deposit"
                                           name="payment_methods[<?= sanitize($code) ?>][allow_deposit]"
                                           <?= $method['allow_deposit'] ? 'checked' : '' ?>
                                           <?= $isStripe ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="payment-<?= sanitize($code) ?>-deposit">Acompte</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="payment-<?= sanitize($code) ?>-balance"
                                           name="payment_methods[<?= sanitize($code) ?>][allow_balance]"
                                           <?= $method['allow_balance'] ? 'checked' : '' ?>
                                           <?= $isStripe ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="payment-<?= sanitize($code) ?>-balance">Solde</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="payment-<?= sanitize($code) ?>-single"
                                           name="<?= $isStripe ? '' : 'payment_methods[' . sanitize($code) . '][allow_single_payment]' ?>"
                                           <?= $method['allow_single_payment'] ? 'checked' : '' ?>
                                           <?= $isStripe ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="payment-<?= sanitize($code) ?>-single">Paiement unique</label>
                                </div>
                            </div>
                            <?php if ($isStripe): ?>
                                <div class="form-text mt-2">La CB en ligne V1 est limitée au paiement unique intégral.</div>
                            <?php endif; ?>
                        </fieldset>

                        <label class="form-label" for="payment-<?= sanitize($code) ?>-instructions">Instructions client</label>
                        <textarea class="form-control" rows="3" maxlength="2000"
                                  id="payment-<?= sanitize($code) ?>-instructions"
                                  name="payment_methods[<?= sanitize($code) ?>][instructions]"
                                  placeholder="Ex. coordonnées bancaires, consignes de remise du chèque…"><?= sanitize($method['instructions']) ?></textarea>
                        <div class="form-text">Affichées au checkout quand la méthode y est disponible.</div>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="btn btn-brand">
            <i class="bi bi-save me-1"></i>Enregistrer les moyens de paiement
        </button>
    </div>
</form>
