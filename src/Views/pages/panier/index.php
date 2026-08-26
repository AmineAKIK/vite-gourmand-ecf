<?php $pageTitle = buildPageTitle('Votre panier'); ?>
<div class="container py-5 panier-page" style="max-width:900px">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="mb-0">Votre panier</h1>
        <a href="/menus" class="btn btn-outline-secondary btn-sm">Continuer mes achats</a>
    </div>

    <?php if (empty($panier)): ?>
        <div class="card p-5 text-center">
            <i class="bi bi-cart display-4 text-muted mb-3"></i>
            <h2 class="h5 mb-2">Votre panier est vide</h2>
            <p class="text-muted mb-4">Ajoutez des menus pour composer votre prestation.</p>
            <a href="/menus" class="btn btn-brand">Découvrir nos menus</a>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <div class="card mb-4">
                <div class="card-body p-0">
                    <?php foreach ($panier as $i => $item): ?>
                        <div class="d-flex align-items-start gap-3 p-3 <?= $i > 0 ? 'border-top' : '' ?> border-subtle">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold"><?= sanitize($item['titre']) ?></div>
                                <div class="text-muted small mt-1"><?= (int) $item['nombre_personne'] ?> personnes · <?= sanitize(formatPrice($item['prix_par_personne'])) ?>/pers.</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="fw-bold text-brand"><?= sanitize(formatMoneyCents(\App\Domain\Money::fromDecimal((string) $item['prix_par_personne']) * (int) $item['nombre_personne'])) ?></div>
                                <form method="POST" action="/panier/retirer" class="mt-1">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="index" value="<?= $i ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" aria-label="Retirer <?= sanitize($item['titre']) ?>"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="POST" action="/panier/vider" class="mb-4">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary" data-confirm="Vider tout le panier ?"><i class="bi bi-x-circle me-1"></i>Vider le panier</button>
            </form>

            <form method="POST" action="/commande" id="form-panier">
                <?= csrfField() ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><span class="badge bg-brand me-2">1</span>Informations client</h2>
                        <?php $user = currentUser(); $userFull = \App\Models\UserModel::findById($user['id']); ?>
                        <div class="row g-3">
                            <div class="col-12 col-lg-6"><label class="form-label">Prénom</label><input type="text" class="form-control" value="<?= sanitize($userFull['prenom'] ?? '') ?>" disabled></div>
                            <div class="col-12 col-lg-6"><label class="form-label">Nom</label><input type="text" class="form-control" value="<?= sanitize($userFull['nom'] ?? '') ?>" disabled></div>
                            <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= sanitize($userFull['email'] ?? '') ?>" disabled></div>
                            <div class="col-12"><label class="form-label">Téléphone</label><input type="tel" class="form-control" value="<?= sanitize($userFull['telephone'] ?? '') ?>" disabled></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3 d-flex align-items-center gap-2"><span class="badge bg-brand">2</span><span>Prestation <small class="d-block text-muted fw-normal">Lieu, date et heure</small></span></h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="adresse_livraison" class="form-label">Adresse <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="adresse_livraison" name="adresse_livraison" autocomplete="street-address" required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="ville_livraison" class="form-label">Ville <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ville_livraison" name="ville_livraison" autocomplete="address-level2" required>
                                <div class="form-text"><?= sanitize(deliveryPricingLabel()) ?></div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="code_postal_livraison" class="form-label">Code postal <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_postal_livraison" name="code_postal_livraison" inputmode="numeric" pattern="[0-9]{5}" autocomplete="postal-code" required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="date_prestation" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_prestation" name="date_prestation" min="<?= sanitize(tomorrowDateInput()) ?>" required>
                                <div id="dispo-indicator" class="mt-1 small" aria-live="polite"></div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="heure_livraison" class="form-label">Heure souhaitée <span class="text-danger">*</span></label>
                                <select class="form-select" id="heure_livraison" name="heure_livraison" required>
                                    <option value="">— Choisir une heure —</option>
                                    <?php for ($h = 7; $h <= 22; $h++): ?>
                                        <option value="<?= sprintf('%02d:00', $h) ?>"><?= sprintf('%02dh00', $h) ?></option>
                                        <?php if ($h < 22): ?><option value="<?= sprintf('%02d:30', $h) ?>"><?= sprintf('%02dh30', $h) ?></option><?php endif; ?>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3 d-flex align-items-center gap-2"><span class="badge bg-brand">3</span><span>Mode de paiement <small class="d-block text-muted fw-normal">Comment souhaitez-vous régler ?</small></span></h2>
                        <?php if (empty($paymentMethods)): ?>
                            <div class="alert alert-warning mb-0" role="alert">
                                Aucun moyen de paiement n’est actuellement disponible. Contactez le traiteur avant de finaliser la commande.
                            </div>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($paymentMethods as $index => $mode): ?>
                                    <div class="col-12 col-sm-6">
                                        <label class="d-flex align-items-start gap-2 border rounded p-3 h-100">
                                            <input type="radio" name="mode_paiement" value="<?= sanitize($mode['code']) ?>" class="form-check-input mt-1" required <?= $index === 0 ? 'checked' : '' ?>>
                                            <span class="small">
                                                <span class="fw-medium d-block"><?= sanitize($mode['label']) ?></span>
                                                <?php if (!empty($mode['instructions'])): ?>
                                                    <span class="text-muted d-block mt-1"><?= sanitize($mode['instructions']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (($mode['provider'] ?? null) === 'stripe'): ?><i class="bi bi-shield-lock ms-auto text-success" title="Paiement sécurisé en ligne"></i><?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3 d-flex align-items-center gap-2"><span class="badge bg-brand">4</span><span>Remarques <small class="d-block text-muted fw-normal">Informations utiles pour le traiteur (optionnel)</small></span></h2>
                        <textarea class="form-control" name="instructions" id="instructions" rows="3" maxlength="1000" placeholder="Allergies, accès au lieu de livraison, instructions particulières…"><?= sanitize($_SESSION['checkout_instructions'] ?? '') ?></textarea>
                        <div class="form-text text-end"><span id="instructions-count">0</span>/1000</div>
                    </div>
                </div>

                <div class="d-grid"><button type="submit" class="btn btn-brand btn-lg" id="btn-finaliser" disabled><i class="bi bi-cart-check me-2"></i>Finaliser la commande</button></div>
            </form>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card position-sticky" style="top:6rem">
                <div class="card-body">
                    <h2 class="h5 mb-3">Récapitulatif</h2>
                    <?php
                    $totalBrutCents = 0;
                    foreach ($panier as $item) {
                        $totalBrutCents += \App\Domain\Money::fromDecimal((string) $item['prix_par_personne']) * (int) $item['nombre_personne'];
                    }
                    ?>
                    <table class="table table-sm mb-3">
                        <?php foreach ($panier as $item): ?>
                            <?php $prixLigneCents = \App\Domain\Money::fromDecimal((string) $item['prix_par_personne']) * (int) $item['nombre_personne']; ?>
                            <tr>
                                <td class="text-muted small"><?= sanitize($item['titre']) ?><br><span class="text-muted" style="font-size:.75rem"><?= (int) $item['nombre_personne'] ?> pers. · <?= sanitize(formatPrice($item['prix_par_personne'])) ?>/pers.</span></td>
                                <td class="text-end fw-medium text-nowrap"><?= sanitize(formatMoneyCents($prixLigneCents)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="border-top"><td class="text-muted">Sous-total menus</td><td class="text-end fw-medium"><?= sanitize(formatMoneyCents($totalBrutCents)) ?></td></tr>
                        <tr><td class="text-muted">Livraison</td><td class="text-end" id="recap-livraison">—</td></tr>
                        <tr id="recap-remise-row" class="text-success" style="display:none"><td><i class="bi bi-tag me-1"></i>Réduction (<?= (int) reductionTauxPourcentage() ?>%)</td><td class="text-end fw-medium" id="recap-remise">—</td></tr>
                        <tr class="border-top fw-bold"><td>Total</td><td class="text-end text-brand" id="recap-total">—</td></tr>
                    </table>
                    <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Le total sera confirmé après saisie de votre ville de livraison.</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($panier)): ?>
<script nonce="<?= cspNonce() ?>">
const adresseInput = document.getElementById('adresse_livraison');
const villeInput = document.getElementById('ville_livraison');
const codePostalInput = document.getElementById('code_postal_livraison');
const dateInput = document.getElementById('date_prestation');
const heureInput = document.getElementById('heure_livraison');
const submitBtn = document.getElementById('btn-finaliser');
const paymentInputs = Array.from(document.querySelectorAll('input[name="mode_paiement"]'));
const totalBrutCents = <?= json_encode($totalBrutCents) ?>;
const reductionSeuilCents = <?= json_encode(reductionSeuilCents()) ?>;
const reductionTauxBasisPoints = <?= json_encode(reductionTauxPourcentage() * 100) ?>;
let reqId = 0;
let livraisonOk = false;
let livraisonController = null;
let livraisonDebounceTimer = null;

function checkForm() {
    const date = dateInput ? dateInput.value.trim() : '';
    const heure = heureInput ? heureInput.value.trim() : '';
    const paymentSelected = paymentInputs.some(input => input.checked);
    if (submitBtn) submitBtn.disabled = !(livraisonOk && date && heure && paymentSelected);
}

async function updateLivraison() {
    const id = ++reqId;
    const adresse = adresseInput ? adresseInput.value.trim() : '';
    const ville = villeInput ? villeInput.value.trim() : '';
    const codePostal = codePostalInput ? codePostalInput.value.trim() : '';
    if (!adresse || !ville || !codePostal) {
        if (livraisonController) livraisonController.abort();
        document.getElementById('recap-livraison').textContent = '—';
        document.getElementById('recap-total').textContent = '—';
        livraisonOk = false;
        checkForm();
        return;
    }

    document.getElementById('recap-livraison').textContent = 'Calcul...';
    document.getElementById('recap-total').textContent = '—';
    try {
        if (livraisonController) livraisonController.abort();
        livraisonController = new AbortController();
        const params = new URLSearchParams({adresse, ville, code_postal: codePostal});
        const data = await window.tugeresFetchJson('/livraison/calcul?' + params.toString(), {signal: livraisonController.signal});
        if (id !== reqId) return;
        const livraisonCents = Number(data.prix_cents);
        const remiseRow = document.getElementById('recap-remise-row');
        let remiseCents = 0;
        if (reductionSeuilCents > 0 && totalBrutCents >= reductionSeuilCents) {
            remiseCents = Math.round(totalBrutCents * reductionTauxBasisPoints / 10000);
            document.getElementById('recap-remise').textContent = '-' + (remiseCents / 100).toFixed(2) + ' €';
            remiseRow.style.display = '';
        } else {
            remiseRow.style.display = 'none';
        }
        document.getElementById('recap-livraison').textContent = (livraisonCents / 100).toFixed(2) + ' €';
        document.getElementById('recap-total').textContent = ((totalBrutCents - remiseCents + livraisonCents) / 100).toFixed(2) + ' €';
        livraisonOk = true;
        checkForm();
    } catch (error) {
        if (window.tugeresIsAbortError && window.tugeresIsAbortError(error)) return;
        if (id !== reqId) return;
        document.getElementById('recap-livraison').textContent = error.message || '—';
        document.getElementById('recap-total').textContent = '—';
        livraisonOk = false;
        checkForm();
    }
}

function scheduleLivraison() {
    clearTimeout(livraisonDebounceTimer);
    livraisonDebounceTimer = setTimeout(updateLivraison, 450);
}

[adresseInput, villeInput, codePostalInput].forEach(input => {
    if (input) input.addEventListener('input', scheduleLivraison);
});

const dispoIndicator = document.getElementById('dispo-indicator');
async function checkDispo() {
    const date = dateInput ? dateInput.value.trim() : '';
    if (!date || !dispoIndicator) { checkForm(); return; }
    try {
        const data = await window.tugeresFetchJson('/commande/disponibilite?date=' + encodeURIComponent(date));
        if (data.complet) {
            dispoIndicator.innerHTML = '<span class="text-danger"><i class="bi bi-calendar-x me-1"></i>Date complète — choisissez une autre date.</span>';
            if (submitBtn) submitBtn.disabled = true;
        } else if (data.max > 0) {
            const restant = data.max - data.count;
            dispoIndicator.innerHTML = '<span class="text-warning"><i class="bi bi-calendar-check me-1"></i>' + restant + ' place(s) restante(s) ce jour.</span>';
            checkForm();
        } else {
            dispoIndicator.innerHTML = '';
            checkForm();
        }
    } catch {
        dispoIndicator.innerHTML = '';
        checkForm();
    }
}

dateInput && dateInput.addEventListener('change', checkDispo);
heureInput && heureInput.addEventListener('change', checkForm);
paymentInputs.forEach(input => input.addEventListener('change', checkForm));
window.addEventListener('load', updateLivraison);
checkForm();

const instructionsArea = document.getElementById('instructions');
const instructionsCount = document.getElementById('instructions-count');
if (instructionsArea && instructionsCount) {
    instructionsCount.textContent = instructionsArea.value.length;
    instructionsArea.addEventListener('input', () => { instructionsCount.textContent = instructionsArea.value.length; });
}
</script>
<?php endif; ?>
