<?php $pageTitle = 'Commander - Vite & Gourmand'; ?>
<div class="container py-5" style="max-width:700px">
    <h1 class="mb-4">Passer une commande</h1>

    <form method="POST" action="/commande" id="form-commande" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

        <!-- Étape 1 : Infos client (pré-remplies) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><span class="badge bg-vg me-2">1</span>Informations client</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="client_prenom" class="form-label">Prénom</label>
                        <input type="text" class="form-control" id="client_prenom" value="<?= sanitize($user['prenom']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
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
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><span class="badge bg-vg me-2">2</span>Lieu et date de la prestation</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="adresse_livraison" class="form-label">Adresse <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="adresse_livraison" name="adresse_livraison" required>
                    </div>
                    <div class="col-md-6">
                        <label for="ville_livraison" class="form-label">Ville <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ville_livraison" name="ville_livraison" required>
                        <div class="form-text">Livraison gratuite à Bordeaux. 5€ + 0,59€/km au-delà.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="code_postal_livraison" class="form-label">Code postal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code_postal_livraison" name="code_postal_livraison" required>
                    </div>
                    <div class="col-md-6">
                        <label for="date_prestation" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_prestation" name="date_prestation" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="heure_livraison" class="form-label">Heure souhaitée <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="heure_livraison" name="heure_livraison" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Étape 3 : Menu et personnes -->
        <div class="card border-0 shadow-sm mb-4">
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
                                <?= sanitize($m['titre']) ?> — <?= number_format($m['prix_par_personne'], 2) ?>€/pers. (min. <?= $m['nombre_personne_minimum'] ?> pers.)
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

<script>
const menuSelect    = document.getElementById('menu_id');
const nbPersonnes   = document.getElementById('nombre_personne');
const villeInput    = document.getElementById('ville_livraison');
const conditionsBox = document.getElementById('conditions-menu');
const conditionsTexte = document.getElementById('conditions-texte');
const hintMin       = document.getElementById('hint-min');
const recapDiv      = document.getElementById('recap-prix');
const submitBtn     = document.querySelector('#form-commande button[type="submit"]');
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
    if ((nb - min) >= 5) prixMenu *= 0.9; // -10%

    const ville = villeInput.value.trim().toLowerCase();
    recapDiv.style.removeProperty('display');
    document.getElementById('recap-menu').textContent      = prixMenu.toFixed(2) + ' €';
    document.getElementById('recap-livraison').textContent = 'Calcul...';
    document.getElementById('recap-total').textContent     = '—';
    if (submitBtn) submitBtn.disabled = true;

    try {
        const response = await fetch('/livraison/calcul?ville=' + encodeURIComponent(ville), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (requestId !== recapRequestId) return;
        if (!response.ok || !data.ok) {
            document.getElementById('recap-livraison').textContent = 'Distance impossible';
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

function distanceDepuisBordeaux(ville) {
    const coords = {
        'merignac': [44.8448, -0.6564],
        'mérignac': [44.8448, -0.6564],
        'pessac': [44.8058, -0.6305],
        'talence': [44.8088, -0.5892],
        'begles': [44.8077, -0.5488],
        'bègles': [44.8077, -0.5488],
        'cenon': [44.8558, -0.5328],
        'lormont': [44.8792, -0.5256],
        'floirac': [44.8327, -0.5278],
        'bruges': [44.8829, -0.6120],
        'gradignan': [44.7736, -0.6156],
        "villenave-d'ornon": [44.7733, -0.5679],
        'villenave d ornon': [44.7733, -0.5679],
        'le bouscat': [44.8662, -0.5984],
    };
    if (ville === '' || ville === 'bordeaux') return 0;
    if (!coords[ville]) return null;
    const [lat2, lon2] = coords[ville];
    const lat1 = 44.8378;
    const lon1 = -0.5792;
    const toRad = deg => deg * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return 6371 * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
}

menuSelect.addEventListener('change', updateRecap);
nbPersonnes.addEventListener('input', updateRecap);
villeInput.addEventListener('input', updateRecap);
updateRecap();
</script>
