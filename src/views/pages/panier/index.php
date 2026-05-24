<?php $pageTitle = 'Votre panier - Vite & Gourmand'; ?>
<div class="container py-5" style="max-width:900px">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="mb-0">Votre panier</h1>
        <a href="/menus" class="btn btn-vg-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Continuer mes achats
        </a>
    </div>

    <?php if (empty($panier)): ?>
        <div class="card border-0 shadow-sm p-5 text-center" style="background:var(--vg-creme);">
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
            <div class="card border-0 shadow-sm mb-4" style="background:var(--vg-creme);">
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
                            <div class="fw-bold text-vg"><?= sanitize(formatPrice($item['prix_menu'])) ?></div>
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
                <button type="submit" class="btn btn-sm btn-vg-outline text-danger border-danger"
                        data-confirm="Vider tout le panier ?">
                    <i class="bi bi-x-circle me-1"></i>Vider le panier
                </button>
            </form>

            <!-- Formulaire de livraison -->
            <form method="POST" action="/commande" id="form-panier" novalidate>
                <?= csrfField() ?>

                <div class="card border-0 shadow-sm mb-4" style="background:var(--vg-creme);">
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

                <div class="card border-0 shadow-sm mb-4" style="background:var(--vg-creme);">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <span class="badge me-2" style="background:var(--vg-bordeaux)!important">2</span>
                            Lieu et date de la prestation
                        </h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="adresse_livraison" class="form-label">Adresse <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="adresse_livraison" name="adresse_livraison" required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="ville_livraison" class="form-label">Ville <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ville_livraison" name="ville_livraison" required>
                                <div class="form-text"><?= sanitize(deliveryPricingLabel()) ?></div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="code_postal_livraison" class="form-label">Code postal <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_postal_livraison" name="code_postal_livraison" required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="date_prestation" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_prestation" name="date_prestation"
                                       min="<?= sanitize(tomorrowDateInput()) ?>" required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="heure_livraison" class="form-label">Heure souhaitée <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="heure_livraison" name="heure_livraison"
                                       min="07:00" max="22:00" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-vg btn-lg" id="btn-finaliser">
                        <i class="bi bi-cart-check me-2"></i>Finaliser la commande
                    </button>
                </div>
            </form>
        </div>

        <!-- Colonne droite : récapitulatif -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm sticky-lg-top" style="background:var(--vg-creme); top:74px; z-index:10;">
                <div class="card-body">
                    <h2 class="h5 mb-3">Récapitulatif</h2>

                    <?php
                    $totalMenus = array_sum(array_column($panier, 'prix_menu'));
                    ?>

                    <table class="table table-sm mb-3">
                        <?php foreach ($panier as $item): ?>
                        <tr>
                            <td class="text-muted small"><?= sanitize($item['titre']) ?><br>
                                <span class="text-muted" style="font-size:.75rem"><?= (int)$item['nombre_personne'] ?> pers.</span>
                            </td>
                            <td class="text-end fw-medium text-nowrap"><?= sanitize(formatPrice($item['prix_menu'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="border-top">
                            <td class="text-muted">Sous-total menus</td>
                            <td class="text-end fw-medium"><?= sanitize(formatPrice($totalMenus)) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Livraison</td>
                            <td class="text-end" id="recap-livraison">—</td>
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
const villeInput  = document.getElementById('ville_livraison');
const totalMenus  = <?= json_encode(array_sum(array_column($panier, 'prix_menu'))) ?>;
let reqId = 0;

async function updateLivraison() {
    const id = ++reqId;
    const ville = villeInput ? villeInput.value.trim() : '';

    if (!ville) {
        document.getElementById('recap-livraison').textContent = '—';
        document.getElementById('recap-total').textContent = '—';
        return;
    }

    document.getElementById('recap-livraison').textContent = 'Calcul...';
    document.getElementById('recap-total').textContent = '—';

    try {
        const r = await fetch('/livraison/calcul?ville=' + encodeURIComponent(ville), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();
        if (id !== reqId) return;
        if (!r.ok || !data.ok) {
            document.getElementById('recap-livraison').textContent = 'Ville non reconnue';
            document.getElementById('recap-total').textContent = '—';
            return;
        }
        const livraison = parseFloat(data.prix);
        document.getElementById('recap-livraison').textContent = livraison.toFixed(2) + ' €';
        document.getElementById('recap-total').textContent = (totalMenus + livraison).toFixed(2) + ' €';
    } catch (e) {
        if (id !== reqId) return;
        document.getElementById('recap-livraison').textContent = '—';
        document.getElementById('recap-total').textContent = '—';
    }
}

if (villeInput) {
    villeInput.addEventListener('input', updateLivraison);
}
</script>
