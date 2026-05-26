<?php
$pageTitle = buildPageTitle('Nos Menus');
$selectedGuests = max(0, (int)($filters['nb_personnes'] ?? 0));
?>
<div class="container py-5 menus-page">
    <h1 class="text-center mb-5">Tous les menus</h1>

    <!-- FILTRES -->
    <aside class="filtres-panel mb-5" aria-label="Filtres de recherche">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-funnel me-2"></i>Trouver le bon menu</h2>
            <button id="reset-filtres" class="btn btn-sm btn-vg-outline">
                Réinitialiser
            </button>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl">
                <label for="filtre-theme" class="form-label small fw-medium">Type d'événement</label>
                <select id="filtre-theme" class="form-select filtre">
                    <option value="">Tous les événements</option>
                    <?php foreach ($themes as $t): ?>
                        <option value="<?= $t['theme_id'] ?>" <?= ($filters['theme_id'] ?? '') == $t['theme_id'] ? 'selected' : '' ?>>
                            <?= sanitize($t['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="filtre-regime" class="form-label small fw-medium">Régime alimentaire</label>
                <select id="filtre-regime" class="form-select filtre">
                    <option value="">Toutes les contraintes</option>
                    <?php foreach ($regimes as $r): ?>
                        <option value="<?= $r['regime_id'] ?>" <?= ($filters['regime_id'] ?? '') == $r['regime_id'] ? 'selected' : '' ?>>
                            <?= sanitize($r['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="filtre-personnes" class="form-label small fw-medium">Nombre de convives</label>
                <input type="number" id="filtre-personnes" class="form-control filtre" placeholder="ex : 12" min="1" value="<?= sanitize($filters['nb_personnes'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="filtre-budget" class="form-label small fw-medium">Budget max / personne</label>
                <input type="number" id="filtre-budget" class="form-control filtre" placeholder="Sans limite" min="0" value="<?= sanitize($filters['budget_personne_max'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="filtre-tri" class="form-label small fw-medium">Trier par</label>
                <select id="filtre-tri" class="form-select filtre">
                    <option value="recommande" <?= ($filters['tri'] ?? 'recommande') === 'recommande' ? 'selected' : '' ?>>Recommandés</option>
                    <option value="prix_asc" <?= ($filters['tri'] ?? '') === 'prix_asc' ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="prix_desc" <?= ($filters['tri'] ?? '') === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                    <option value="personnes_asc" <?= ($filters['tri'] ?? '') === 'personnes_asc' ? 'selected' : '' ?>>Minimum convives croissant</option>
                    <option value="personnes_desc" <?= ($filters['tri'] ?? '') === 'personnes_desc' ? 'selected' : '' ?>>Minimum convives décroissant</option>
                </select>
            </div>
        </div>
    </aside>

    <!-- RÉSULTATS -->
    <div id="menus-container" class="row g-4" role="list" aria-live="polite" aria-label="Liste des menus">
        <?php foreach ($menus as $menu): ?>
        <div class="col-12 col-md-6 col-lg-4" role="listitem">
            <article class="card card-menu h-100">
                <img
                    src="<?= sanitize(imageUrl($menu['image_principale'] ?: null)) ?>"
                    class="card-img-top"
                    alt="Illustration du menu <?= sanitize($menu['titre']) ?>"
                    loading="lazy"
                    decoding="async"
                >
                <div class="card-body d-flex flex-column">
                    <h3 class="card-title h5"><?= sanitize($menu['titre']) ?></h3>
                    <p class="card-text text-muted small flex-grow-1">
                        <?= sanitize(substr(html_entity_decode($menu['description'] ?? '', ENT_QUOTES, 'UTF-8'), 0, 120)) ?>...
                    </p>
                    <div class="mt-3">
                        <?php
                            $minimum = (int)($menu['nombre_personne_minimum'] ?? 0);
                            $personnesEstimees = max($selectedGuests, $minimum);
                            $prixEstime = round((float)($menu['prix_par_personne'] ?? 0) * $personnesEstimees, 2);
                        ?>
                        <div>
                            <span class="prix-tag"><?= sanitize(formatPrice($menu['prix_par_personne'] ?? 0)) ?></span>
                            <small class="text-muted">/ personne</small>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            <?php if ($selectedGuests > 0): ?>
                                Estimation pour <?= $personnesEstimees ?> personnes : <strong><?= sanitize(formatPrice($prixEstime)) ?></strong>
                            <?php else: ?>
                                À partir de <?= $minimum ?> personnes : <strong><?= sanitize(formatPrice($prixEstime)) ?></strong>
                            <?php endif; ?>
                        </p>
                        <?php if ($menu['quantite_restante'] !== null): ?>
                            <?php if ((int)$menu['quantite_restante'] <= 0): ?>
                                <p class="small text-danger fw-semibold mt-2 mb-0"><i class="bi bi-x-circle me-1"></i>Plus disponible</p>
                            <?php elseif ((int)$menu['quantite_restante'] <= 3): ?>
                                <p class="small text-warning fw-semibold mt-2 mb-0"><i class="bi bi-exclamation-circle me-1"></i>Plus que <?= (int)$menu['quantite_restante'] ?> réservation(s) disponible(s)</p>
                            <?php else: ?>
                                <p class="small text-success mt-2 mb-0"><i class="bi bi-check-circle me-1"></i><?= (int)$menu['quantite_restante'] ?> réservations disponibles</p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="/menus/detail?id=<?= (int)$menu['menu_id'] ?>" class="btn btn-vg btn-sm mt-3">
                            Voir le détail
                        </a>
                    </div>
                </div>
            </article>
        </div>
        <?php endforeach; ?>
        <?php if (empty($menus)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-search display-4 mb-3 d-block"></i>
                <p>Aucun menu ne correspond à vos critères.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
// Filtres dynamiques sans rechargement de page
function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

let debounceTimer;
let menusRequestController = null;
const filtres = document.querySelectorAll('.filtre');

document.getElementById('reset-filtres').addEventListener('click', () => {
    document.getElementById('filtre-theme').value = '';
    document.getElementById('filtre-regime').value = '';
    document.getElementById('filtre-personnes').value = '';
    document.getElementById('filtre-budget').value = '';
    document.getElementById('filtre-tri').value = 'recommande';
    fetchMenus();
});

filtres.forEach(f => f.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchMenus, 400);
}));
filtres.forEach(f => f.addEventListener('change', fetchMenus));

function fetchMenus() {
    if (menusRequestController) {
        menusRequestController.abort();
    }
    menusRequestController = new AbortController();
    const requestController = menusRequestController;
    const params = new URLSearchParams({
        theme_id:             document.getElementById('filtre-theme').value,
        regime_id:            document.getElementById('filtre-regime').value,
        nb_personnes:         document.getElementById('filtre-personnes').value,
        budget_personne_max:  document.getElementById('filtre-budget').value,
        tri:                  document.getElementById('filtre-tri').value,
    });
    const requestedGuests = parseInt(document.getElementById('filtre-personnes').value, 10) || 0;

    const container = document.getElementById('menus-container');
    container.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-vg" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

    window.vgFetchJson('/menus?' + params.toString(), {
        signal: requestController.signal
    })
    .then(payload => {
        if (requestController.signal.aborted) return;
        const menus = Array.isArray(payload) ? payload : (payload.data || []);
        if (menus.length === 0) {
            container.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="bi bi-search display-4 mb-3 d-block"></i><p>Aucun menu ne correspond à vos critères.</p></div>';
            return;
        }
        container.innerHTML = menus.map(m => `
            <div class="col-12 col-md-6 col-lg-4" role="listitem">
                <article class="card card-menu h-100">
                    <img
                        src="${(m.image_principale && (m.image_principale.startsWith('http://') || m.image_principale.startsWith('https://'))) ? esc(m.image_principale) : '/' + esc(m.image_principale || 'images/menu-placeholder.webp')}"
                        class="card-img-top"
                        alt="Illustration du menu ${esc(m.titre)}"
                        loading="lazy"
                        decoding="async"
                    >
                    <div class="card-body d-flex flex-column">
                        <h3 class="card-title h5">${esc(m.titre)}</h3>
                        <p class="card-text text-muted small flex-grow-1">${m.description ? esc(m.description.substring(0, 120)) + '...' : ''}</p>
                        <div class="mt-3">
                            <div>
                                <span class="prix-tag">${Number(m.prix_par_personne).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €</span>
                                <small class="text-muted">/ personne</small>
                            </div>
                            <p class="small text-muted mt-2 mb-0">
                                ${requestedGuests > 0
                                    ? `Estimation pour ${parseInt(m.personnes_estimees)} personnes : <strong>${Number(m.prix_estime).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €</strong>`
                                    : `À partir de ${parseInt(m.nombre_personne_minimum)} personnes : <strong>${Number(m.prix_estime).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €</strong>`
                                }
                            </p>
                            ${m.quantite_restante !== null ? (
                                parseInt(m.quantite_restante) <= 0
                                    ? `<p class="small text-danger fw-semibold mt-2 mb-0"><i class="bi bi-x-circle me-1"></i>Plus disponible</p>`
                                    : parseInt(m.quantite_restante) <= 3
                                        ? `<p class="small text-warning fw-semibold mt-2 mb-0"><i class="bi bi-exclamation-circle me-1"></i>Plus que ${parseInt(m.quantite_restante)} place(s)</p>`
                                        : `<p class="small text-success mt-2 mb-0"><i class="bi bi-check-circle me-1"></i>${parseInt(m.quantite_restante)} places disponibles</p>`
                            ) : ''}
                            <a href="/menus/detail?id=${parseInt(m.menu_id)}" class="btn btn-vg btn-sm mt-3">
                                Voir le détail
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        `).join('');
    })
    .catch(error => {
        if (window.vgIsAbortError && window.vgIsAbortError(error)) return;
        container.innerHTML = '<div class="col-12 text-center text-danger py-3">Erreur lors du chargement.</div>';
    });
}
</script>
