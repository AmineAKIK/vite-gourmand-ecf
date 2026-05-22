<?php
$pageTitle = 'Nos Menus - Vite & Gourmand';
?>
<div class="container py-5">
    <h1 class="text-center mb-5">Tous les menus</h1>

    <!-- FILTRES -->
    <aside class="filtres-panel mb-4" aria-label="Filtres de recherche">
        <h2 class="h6 mb-3">Filtres</h2>
        <div class="row g-3">
            <div class="col-md">
                <label for="filtre-prix-min" class="form-label small">Prix min (€)</label>
                <input type="number" id="filtre-prix-min" class="form-control form-control-sm filtre" placeholder="0" value="<?= sanitize($filters['prix_min'] ?? '') ?>">
            </div>
            <div class="col-md">
                <label for="filtre-prix-max" class="form-label small">Prix max (€)</label>
                <input type="number" id="filtre-prix-max" class="form-control form-control-sm filtre" placeholder="999" value="<?= sanitize($filters['prix_max'] ?? '') ?>">
            </div>
            <div class="col-md">
                <label for="filtre-theme" class="form-label small">Thème</label>
                <select id="filtre-theme" class="form-select form-select-sm filtre">
                    <option value="">Tous</option>
                    <?php foreach ($themes as $t): ?>
                        <option value="<?= $t['theme_id'] ?>" <?= ($filters['theme_id'] ?? '') == $t['theme_id'] ? 'selected' : '' ?>>
                            <?= sanitize($t['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md">
                <label for="filtre-regime" class="form-label small">Régime</label>
                <select id="filtre-regime" class="form-select form-select-sm filtre">
                    <option value="">Tous</option>
                    <?php foreach ($regimes as $r): ?>
                        <option value="<?= $r['regime_id'] ?>" <?= ($filters['regime_id'] ?? '') == $r['regime_id'] ? 'selected' : '' ?>>
                            <?= sanitize($r['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md">
                <label for="filtre-personnes" class="form-label small">Nb personnes min.</label>
                <input type="number" id="filtre-personnes" class="form-control form-control-sm filtre" placeholder="ex: 4" value="<?= sanitize($filters['nb_personnes'] ?? '') ?>">
            </div>
        </div>
    </aside>

    <!-- RÉSULTATS -->
    <div id="menus-container" class="row g-4" role="list" aria-live="polite" aria-label="Liste des menus">
        <?php foreach ($menus as $menu): ?>
        <div class="col-md-4" role="listitem">
            <article class="card card-menu h-100">
                <img
                    src="/<?= sanitize($menu['image_principale'] ?: 'images/menu-placeholder.webp') ?>"
                    class="card-img-top"
                    alt="Illustration du menu <?= sanitize($menu['titre']) ?>"
                    loading="lazy"
                    decoding="async"
                >
                <div class="card-body d-flex flex-column">
                    <h3 class="card-title h5"><?= sanitize($menu['titre']) ?></h3>
                    <p class="card-text text-muted small flex-grow-1">
                        <?= nl2br(sanitize(substr($menu['description'] ?? '', 0, 120))) ?>...
                    </p>
                    <div class="mt-3">
                        <p class="small mb-2">
                            Minimum : <?= (int)$menu['nombre_personne_minimum'] ?> personnes
                        </p>
                        <div>
                            <span class="prix-tag"><?= number_format($menu['prix_par_personne'] * $menu['nombre_personne_minimum'], 2) ?> €</span>
                            <small class="text-muted">pour <?= (int)$menu['nombre_personne_minimum'] ?> personnes</small>
                        </div>
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

<script>
// Filtres dynamiques sans rechargement de page
function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

let debounceTimer;
const filtres = document.querySelectorAll('.filtre');

filtres.forEach(f => f.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchMenus, 400);
}));

function fetchMenus() {
    const params = new URLSearchParams({
        prix_min:     document.getElementById('filtre-prix-min').value,
        prix_max:     document.getElementById('filtre-prix-max').value,
        theme_id:     document.getElementById('filtre-theme').value,
        regime_id:    document.getElementById('filtre-regime').value,
        nb_personnes: document.getElementById('filtre-personnes').value,
    });

    const container = document.getElementById('menus-container');
    container.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-vg" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

    fetch('/menus?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(menus => {
        if (menus.length === 0) {
            container.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="bi bi-search display-4 mb-3 d-block"></i><p>Aucun menu ne correspond à vos critères.</p></div>';
            return;
        }
        container.innerHTML = menus.map(m => `
            <div class="col-md-4" role="listitem">
                <article class="card card-menu h-100">
                    <img
                        src="/${esc(m.image_principale || 'images/menu-placeholder.webp')}"
                        class="card-img-top"
                        alt="Illustration du menu ${esc(m.titre)}"
                        loading="lazy"
                        decoding="async"
                    >
                    <div class="card-body d-flex flex-column">
                        <h3 class="card-title h5">${esc(m.titre)}</h3>
                        <p class="card-text text-muted small flex-grow-1">${m.description ? esc(m.description.substring(0, 120)) + '...' : ''}</p>
                        <div class="mt-3">
                            <p class="small mb-2">Minimum : ${parseInt(m.nombre_personne_minimum)} personnes</p>
                            <div>
                                <span class="prix-tag">${(parseFloat(m.prix_par_personne) * parseInt(m.nombre_personne_minimum)).toFixed(2)} €</span>
                                <small class="text-muted">pour ${parseInt(m.nombre_personne_minimum)} personnes</small>
                            </div>
                            <a href="/menus/detail?id=${parseInt(m.menu_id)}" class="btn btn-vg btn-sm mt-3">
                                Voir le détail
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        `).join('');
    })
    .catch(() => {
        container.innerHTML = '<div class="col-12 text-center text-danger py-3">Erreur lors du chargement.</div>';
    });
}
</script>
