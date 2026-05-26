<?php $pageTitle = 'Votre panier - Vite & Gourmand'; ?>
<div class="container py-5 panier-page" style="max-width:900px">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="mb-0">Votre panier</h1>
        <a href="/menus" class="btn btn-vg-outline btn-sm">
            Continuer mes achats
        </a>
    </div>

    <?php if (empty($panier)): ?>
        <div class="card panier-panel p-5 text-center">
            <i class="bi bi-cart display-4 text-muted mb-3"></i>
            <h2 class="h5 mb-2">Votre panier est vide</h2>
            <p class="text-muted mb-4">Ajoutez des menus pour composer votre prestation.</p>
            <a href="/menus" class="btn btn-vg">Découvrir nos menus</a>
        </div>
    <?php else: ?>

    <div class="row g-4">

        <!-- Colonne gauche : articles -->
        <div class="col-12 col-lg-7">

            <!-- Liste des menus -->
            <div class="card panier-panel mb-4">
                <div class="card-body p-0">
                    <?php foreach ($panier as $i => $item): ?>
                    <div class="d-flex align-items-start gap-3 p-3 <?= $i > 0 ? 'border-top' : '' ?>" style="border-color:var(--vg-border)!important">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold"><?= sanitize($item['titre']) ?></div>
                            <div class="text-muted small mt-1">
                                <?= (int)$item['nombre_personne'] ?> personnes
                                · <?= sanitize(formatPrice($item['prix_par_personne'])) ?>/pers.
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fw-bold text-vg"><?= sanitize(formatPrice(round((float)$item['prix_par_personne'] * (int)$item['nombre_personne'], 2))) ?></div>
                            <form method="POST" action="/panier/retirer" class="mt-1">
                                <?= csrfField() ?>
                                <input type="hidden" name="index" value="<?= $i ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Vider le panier -->
            <form method="POST" action="/panier/vider" class="mb-4">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-sm btn-vg-outline"
                        data-confirm="Vider tout le panier ?">
                    <i class="bi bi-x-circle me-1"></i>Vider le panier
                </button>
            </form>

            <!-- Formulaire de livraison -->
            <form method="POST" action="/commande" id="form-panier">
                <?= csrfField() ?>

                <div class="card panier-panel mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <span class="badge bg-vg me-2" style="background:var(--vg-bordeaux)!important">1</span>
                            Informations client
                        </h2>
                        <?php $user = currentUser(); $userFull = \UserModel::findById($user['id']); ?>
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <label class="form-label">Prénom</label>
                                <input type="text" class="form-control" value="<?= sanitize($userFull['prenom'] ?? '') ?>" disabled>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label class="form-label">Nom</label>
                                <input type="text" class="form-control" value="<?= sanitize($userFull['nom'] ?? '') ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= sanitize($userFull['email'] ?? '') ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" value="<?= sanitize($userFull['telephone'] ?? '') ?>" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card panier-panel mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3 checkout-step-title">
                            <span class="checkout-step-badge">2</span>
                            <span>
                                <span class="checkout-step-main">Prestation</span>
                                <span class="checkout-step-subtitle">Lieu, date et heure</span>
                            </span>
                        </h2>
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
                                <input type="date" class="form-control" id="date_prestation" name="date_prestation"
                                       min="<?= sanitize(tomorrowDateInput()) ?>" required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="heure_livraison" class="form-label">Heure souhaitée <span class="text-danger">*</span></label>
                                <select class="form-select" id="heure_livraison" name="heure_livraison" required>
                                    <option value="">— Choisir une heure —</option>
                                    <?php for ($h = 7; $h <= 22; $h++): ?>
                                        <option value="<?= sprintf('%02d:00', $h) ?>"><?= sprintf('%02dh00', $h) ?></option>
                                        <?php if ($h < 22): ?>
                                        <option value="<?= sprintf('%02d:30', $h) ?>"><?= sprintf('%02dh30', $h) ?></option>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card panier-panel mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3 checkout-step-title">
                            <span class="checkout-step-badge">3</span>
                            <span>
                                <span class="checkout-step-main">Mode de paiement</span>
                                <span class="checkout-step-subtitle">Comment souhaitez-vous régler ?</span>
                            </span>
                        </h2>
                        <div class="row g-2">
                            <?php
                            $modesActifs = db()->fetchAll("SELECT * FROM mode_paiement WHERE actif = 1 ORDER BY mode_id");
                            foreach ($modesActifs as $mode):
                            ?>
                            <div class="col-12 col-sm-6">
                                <label class="panier-mode-label d-flex align-items-center gap-2 border rounded p-3 cursor-pointer">
                                    <input type="radio" name="mode_paiement" value="<?= sanitize($mode['code']) ?>"
                                           class="form-check-input mt-0" required
                                           <?= $mode['code'] === 'virement' ? 'checked' : '' ?>>
                                    <span class="small fw-medium"><?= sanitize($mode['libelle']) ?></span>
                                    <?php if ($mode['code'] === 'cb_online'): ?>
                                    <i class="bi bi-shield-lock ms-auto text-success" title="Paiement sécurisé par Stripe"></i>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-vg btn-lg" id="btn-finaliser" disabled>
                        <i class="bi bi-cart-check me-2"></i>Finaliser la commande
                    </button>
                </div>
            </form>
        </div>

        <!-- Colonne droite : récapitulatif -->
        <div class="col-12 col-lg-5">
            <div class="card panier-panel panier-recap-sticky">
                <div class="card-body">
                    <h2 class="h5 mb-3">Récapitulatif</h2>

                    <?php
                    // Total brut = somme des (prix/pers × nb_personnes) sans réduction
                    $totalBrut = 0.0;
                    foreach ($panier as $item) {
                        $totalBrut += round((float)$item['prix_par_personne'] * (int)$item['nombre_personne'], 2);
                    }
                    $totalBrut = round($totalBrut, 2);
                    ?>

                    <table class="table table-sm mb-3">
                        <?php foreach ($panier as $item): ?>
                        <?php $prixLigne = round((float)$item['prix_par_personne'] * (int)$item['nombre_personne'], 2); ?>
                        <tr>
                            <td class="text-muted small"><?= sanitize($item['titre']) ?><br>
                                <span class="text-muted" style="font-size:.75rem"><?= (int)$item['nombre_personne'] ?> pers. · <?= sanitize(formatPrice($item['prix_par_personne'])) ?>/pers.</span>
                            </td>
                            <td class="text-end fw-medium text-nowrap"><?= sanitize(formatPrice($prixLigne)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="border-top">
                            <td class="text-muted">Sous-total menus</td>
                            <td class="text-end fw-medium"><?= sanitize(formatPrice($totalBrut)) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Livraison</td>
                            <td class="text-end" id="recap-livraison">—</td>
                        </tr>
                        <tr id="recap-remise-row" class="text-success" style="display:none">
                            <td><i class="bi bi-tag me-1"></i>Réduction (<?= (int)reductionTauxPourcentage() ?>%)</td>
                            <td class="text-end fw-medium" id="recap-remise">—</td>
                        </tr>
                        <tr class="border-top fw-bold">
                            <td>Total</td>
                            <td class="text-end prix-tag" id="recap-total">—</td>
                        </tr>
                    </table>

                    <div class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Le total sera confirmé après saisie de votre ville de livraison.
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.row -->
    <?php endif; ?>
</div>

<script nonce="<?= cspNonce() ?>">
const adresseInput    = document.getElementById('adresse_livraison');
const villeInput      = document.getElementById('ville_livraison');
const codePostalInput = document.getElementById('code_postal_livraison');
const dateInput       = document.getElementById('date_prestation');
const heureInput      = document.getElementById('heure_livraison');
const submitBtn       = document.getElementById('btn-finaliser');
// totalBrut = somme brute sans réduction (correspond à la logique PricingService côté PHP)
const totalBrut        = <?= json_encode($totalBrut) ?>;
const reductionSeuil   = <?= json_encode(reductionSeuilMontant()) ?>;
const reductionTaux    = <?= json_encode(reductionTauxPourcentage() / 100) ?>;
let reqId = 0;
let livraisonOk = false;
let livraisonController = null;
let livraisonDebounceTimer = null;

function checkForm() {
    const date  = dateInput ? dateInput.value.trim() : '';
    const heure = heureInput ? heureInput.value.trim() : '';
    if (submitBtn) submitBtn.disabled = !(livraisonOk && date && heure);
}

async function updateLivraison() {
    const id = ++reqId;
    const adresse    = adresseInput    ? adresseInput.value.trim()    : '';
    const ville      = villeInput      ? villeInput.value.trim()      : '';
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
        const params = new URLSearchParams({ adresse, ville, code_postal: codePostal });
        const data = await window.vgFetchJson('/livraison/calcul?' + params.toString(), {
            signal: livraisonController.signal
        });
        if (id !== reqId) return;
        const livraison = parseFloat(data.prix);
        const remiseRow = document.getElementById('recap-remise-row');
        // Remise calculée sur le total global (pas par ligne) — cohérent avec PricingService
        let remise = 0;
        if (reductionSeuil > 0 && totalBrut >= reductionSeuil) {
            remise = Math.round(totalBrut * reductionTaux * 100) / 100;
            document.getElementById('recap-remise').textContent = '-' + remise.toFixed(2) + ' €';
            remiseRow.style.display = '';
        } else {
            remiseRow.style.display = 'none';
        }
        document.getElementById('recap-livraison').textContent = livraison.toFixed(2) + ' €';
        document.getElementById('recap-total').textContent = (totalBrut - remise + livraison).toFixed(2) + ' €';
        livraisonOk = true;
        checkForm();
    } catch (e) {
        if (window.vgIsAbortError && window.vgIsAbortError(e)) return;
        if (id !== reqId) return;
        document.getElementById('recap-livraison').textContent = e.message || '—';
        document.getElementById('recap-total').textContent = '—';
        livraisonOk = false;
        checkForm();
    }
}

function scheduleLivraison() {
    clearTimeout(livraisonDebounceTimer);
    livraisonDebounceTimer = setTimeout(updateLivraison, 450);
}

[adresseInput, villeInput, codePostalInput].forEach(el => {
    if (el) el.addEventListener('input', scheduleLivraison);
});
[dateInput, heureInput].forEach(el => {
    if (el) el.addEventListener('change', checkForm);
});
window.addEventListener('load', updateLivraison);
checkForm();
</script>
