<?php $pageTitle = 'Commander - Vite & Gourmand'; ?>
<div class="container py-5" style="max-width:700px">
    <h1 class="mb-4">Passer une commande</h1>

    <form method="POST" action="/commande" id="form-commande" novalidate>
        <?= csrfField() ?>

        <!-- Étape 1 : Infos client (pré-remplies) -->
        <div class="card border-0 shadow-sm mb-4" style="background:var(--vg-creme);">
            <div class="card-body">
                <h2 class="h5 mb-3"><span class="badge bg-vg me-2">1</span>Informations client</h2>
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label for="client_prenom" class="form-label">Prénom</label>
                        <input type="text" class="form-control" id="client_prenom" value="<?= sanitize($user['prenom']) ?>" disabled>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="client_nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="client_nom" value="<?= sanitize($user['nom']) ?>" disabled>
                    </div>
                    <div class="col-12">
                        <label for="client_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="client_email" value="<?= sanitize($user['email']) ?>" disabled>
                    </div>
                    <div class="col-12">
                        <label for="client_telephone" class="form-label">Téléphone (GSM)</label>
                        <input type="tel" class="form-control" id="client_telephone"
                               value="<?= sanitize($user['telephone'] ?? '') ?>" disabled
                               aria-label="Téléphone du client">
                    </div>
                </div>
            </div>
        </div>

        <!-- Étape 2 : Lieu et date -->
        <div class="card border-0 shadow-sm mb-4" style="background:var(--vg-creme);">
            <div class="card-body">
                <h2 class="h5 mb-3"><span class="badge bg-vg me-2">2</span>Lieu et date de la prestation</h2>
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
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="heure_livraison" class="form-label">Heure souhaitée <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="heure_livraison" name="heure_livraison" min="07:00" max="22:00" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Étape 3 : Menu et personnes -->
        <div class="card border-0 shadow-sm mb-4" style="background:var(--vg-creme);">
            <div class="card-body">
                <h2 class="h5 mb-3"><span class="badge bg-vg me-2">3</span>Menu et nombre de personnes</h2>
                <div class="mb-3">
                    <label for="menu_id" class="form-label">Menu choisi <span class="text-danger">*</span></label>
                    <select class="form-select" id="menu_id" name="menu_id" required>
                        <option value="">-- Sélectionner un menu --</option>
                        <?php foreach ($menus as $m): ?>
                            <option value="<?= $m['menu_id'] ?>"
                                data-min="<?= $m['nombre_personne_minimum'] ?>"
                                data-prix="<?= $m['prix_par_personne'] ?>"
                                data-conditions="<?= sanitize($m['conditions'] ?? '') ?>"
                                <?= ($menuPreselect && $menuPreselect['menu_id'] == $m['menu_id']) ? 'selected' : '' ?>>
                                <?= sanitize($m['titre']) ?> — <?= sanitize(formatPrice($m['prix_par_personne'])) ?>/pers. (min. <?= (int)$m['nombre_personne_minimum'] ?> pers.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Conditions du menu sélectionné -->
                <div id="conditions-menu" class="alert alert-warning d-none" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i><strong>Conditions :</strong> <span id="conditions-texte"></span>
                </div>

                <div class="mb-3">
                    <label for="nombre_personne" class="form-label">Nombre de personnes <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="nombre_personne" name="nombre_personne" min="1" required>
                    <div id="hint-min" class="form-text"></div>
                </div>
            </div>
        </div>

        <!-- Récapitulatif prix -->
        <div class="card border-warning bg-creme mb-4" id="recap-prix" style="display:none!important">
            <div class="card-body">
                <h2 class="h5 mb-3">Récapitulatif du prix</h2>
                <table class="table table-sm mb-0">
                    <tr><td>Prix menu</td><td class="text-end" id="recap-menu">—</td></tr>
                    <tr><td>Livraison</td><td class="text-end" id="recap-livraison">—</td></tr>
                    <tr class="fw-bold"><td>Total</td><td class="text-end prix-tag" id="recap-total">—</td></tr>
                </table>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-vg btn-lg">
                <i class="bi bi-cart-check me-2"></i>Confirmer la commande
            </button>
        </div>
    </form>
</div>

<script nonce="<?= cspNonce() ?>">
const menuSelect    = document.getElementById('menu_id');
const nbPersonnes   = document.getElementById('nombre_personne');
const adresseInput  = document.getElementById('adresse_livraison');
const villeInput    = document.getElementById('ville_livraison');
const codePostalInput = document.getElementById('code_postal_livraison');
const conditionsBox = document.getElementById('conditions-menu');
const conditionsTexte = document.getElementById('conditions-texte');
const hintMin       = document.getElementById('hint-min');
const recapDiv      = document.getElementById('recap-prix');
const submitBtn     = document.querySelector('#form-commande button[type="submit"]');
const reductionSeuil = <?= json_encode(reductionSeuilMontant()) ?>;
const reductionTaux = <?= json_encode(reductionTauxPourcentage() / 100) ?>;
let recapRequestId  = 0;

async function updateRecap() {
    const requestId = ++recapRequestId;
    const opt = menuSelect.options[menuSelect.selectedIndex];
    if (!opt.value) {
        recapDiv.style.display = 'none';
        if (submitBtn) submitBtn.disabled = false;
        return;
    }

    const prixParPers = parseFloat(opt.dataset.prix);
    const min         = parseInt(opt.dataset.min);
    const nb          = parseInt(nbPersonnes.value) || 0;
    const conditions  = opt.dataset.conditions;

    // Afficher conditions
    if (conditions) {
        conditionsBox.classList.remove('d-none');
        conditionsTexte.textContent = conditions;
    } else {
        conditionsBox.classList.add('d-none');
    }

    hintMin.textContent = `Minimum : ${min} personnes`;
    nbPersonnes.min = min;

    if (nb < min) {
        if (submitBtn) submitBtn.disabled = false;
        return;
    }

    let prixMenu = prixParPers * nb;
    if (prixMenu >= reductionSeuil) prixMenu *= (1 - reductionTaux);

    const adresse = adresseInput.value.trim();
    const ville = villeInput.value.trim();
    const codePostal = codePostalInput.value.trim();
    recapDiv.style.removeProperty('display');
    document.getElementById('recap-menu').textContent      = prixMenu.toFixed(2) + ' €';
    document.getElementById('recap-total').textContent     = '—';
    if (!adresse || !ville || !codePostal) {
        document.getElementById('recap-livraison').textContent = 'Adresse à compléter';
        if (submitBtn) submitBtn.disabled = true;
        return;
    }
    document.getElementById('recap-livraison').textContent = 'Calcul...';
    if (submitBtn) submitBtn.disabled = true;

    try {
        const params = new URLSearchParams({
            adresse: adresse,
            ville: ville,
            code_postal: codePostal,
        });
        const response = await fetch('/livraison/calcul?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (requestId !== recapRequestId) return;
        if (!response.ok || !data.ok) {
            document.getElementById('recap-livraison').textContent = data.message || 'Adresse non reconnue';
            document.getElementById('recap-total').textContent     = '—';
            return;
        }
        const prixLivraison = parseFloat(data.prix);
        document.getElementById('recap-livraison').textContent = prixLivraison.toFixed(2) + ' €';
        document.getElementById('recap-total').textContent     = (prixMenu + prixLivraison).toFixed(2) + ' €';
        if (submitBtn) submitBtn.disabled = false;
    } catch (e) {
        if (requestId !== recapRequestId) return;
        document.getElementById('recap-livraison').textContent = 'Distance impossible';
        document.getElementById('recap-total').textContent     = '—';
    }
}

menuSelect.addEventListener('change', updateRecap);
nbPersonnes.addEventListener('input', updateRecap);
adresseInput.addEventListener('input', updateRecap);
villeInput.addEventListener('input', updateRecap);
codePostalInput.addEventListener('input', updateRecap);
updateRecap();
</script>
