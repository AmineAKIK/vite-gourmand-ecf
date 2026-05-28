<?php
$pageTitle = buildPageTitle('Fiches techniques & Stocks');
$activeTab = $_GET['tab'] ?? 'recettes';
?>

<?php partial('partials/page_title_bar', ['icon' => 'bi-clipboard2-data', 'title' => 'Fiches techniques & Stocks']); ?>

<?php if ($alertes): ?>
<div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
    <div>
        <strong><?= count($alertes) ?> ingrédient(s) sous le seuil d'alerte :</strong>
        <?= implode(', ', array_map(fn($a) => '<strong>' . sanitize($a['libelle']) . '</strong> (' . sanitize(formatPriceInput($a['stock_courant'])) . ' ' . sanitize($a['unite']) . ')', $alertes)) ?>
        — <a href="?tab=stocks" class="alert-link">Voir les stocks</a>
    </div>
</div>
<?php endif; ?>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" id="recetteTabs" role="tablist">
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'recettes' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#pane-recettes" type="button"><i class="bi bi-file-earmark-text me-1"></i>Fiches techniques</button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'ingredients' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#pane-ingredients" type="button"><i class="bi bi-box-seam me-1"></i>Ingrédients</button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'stocks' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#pane-stocks" type="button"><i class="bi bi-archive me-1"></i>Stocks</button></li>
</ul>

<div class="tab-content">

<!-- ============================= FICHES TECHNIQUES ============================= -->
<div class="tab-pane fade <?= $activeTab === 'recettes' ? 'show active' : '' ?>" id="pane-recettes">
    <?php if (!$plats): ?>
        <div class="alert alert-info">Aucun plat dans la base. Créez d'abord des plats dans <a href="/employe/menus">Menus & Plats</a>.</div>
    <?php else: ?>
        <div class="accordion" id="accordionRecettes">
        <?php foreach ($plats as $plat):
            $pid   = (int)$plat['plat_id'];
            $lignes = $recettesByPlat[$pid] ?? [];
            $cout  = $coutsByPlat[$pid] ?? 0;
        ?>
        <div class="accordion-item" id="plat-<?= $pid ?>">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-plat-<?= $pid ?>">
                    <span class="fw-semibold me-2"><?= sanitize($plat['titre']) ?></span>
                    <span class="badge bg-secondary me-2"><?= sanitize($plat['categorie']) ?></span>
                    <?php if ($cout > 0): ?>
                        <span class="badge" style="background:var(--vg-primary)">Coût : <?= sanitize(formatPrice($cout)) ?>/portion</span>
                    <?php else: ?>
                        <span class="badge bg-light text-muted">Aucune recette</span>
                    <?php endif; ?>
                </button>
            </h2>
            <div id="collapse-plat-<?= $pid ?>" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <form method="POST" action="/employe/recette/sauvegarder">
                        <?= csrfField() ?>
                        <input type="hidden" name="plat_id" value="<?= $pid ?>">

                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle" id="recette-table-<?= $pid ?>">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ingrédient</th>
                                        <th style="width:140px">Quantité</th>
                                        <th style="width:80px">Unité</th>
                                        <th style="width:100px">Coût</th>
                                        <th style="width:50px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($lignes as $ligne): ?>
                                <tr>
                                    <td>
                                        <select name="ingredient_id[]" class="form-select form-select-sm" required>
                                            <option value="">— choisir —</option>
                                            <?php foreach ($ingredients as $ing): ?>
                                            <option value="<?= (int)$ing['ingredient_id'] ?>" <?= (int)$ing['ingredient_id'] === (int)$ligne['ingredient_id'] ? 'selected' : '' ?>>
                                                <?= sanitize($ing['libelle']) ?> (<?= sanitize($ing['unite']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="grammage[]" class="form-control form-control-sm" step="0.001" min="0.001" value="<?= sanitize(formatPriceInput($ligne['grammage'])) ?>" required></td>
                                    <td class="text-muted small"><?= sanitize($ligne['unite']) ?></td>
                                    <td class="text-muted small"><?= sanitize(formatPrice((float)$ligne['grammage'] * (float)$ligne['prix_unitaire'])) ?></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex align-items-center gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-add-row" data-target="recette-table-<?= $pid ?>" data-ingredients='<?= json_encode(array_map(fn($i) => ['id' => (int)$i['ingredient_id'], 'libelle' => $i['libelle'], 'unite' => $i['unite']], $ingredients)) ?>'>
                                <i class="bi bi-plus-circle me-1"></i>Ajouter une ligne
                            </button>
                            <?php if ($cout > 0): ?>
                                <span class="text-muted small ms-2">Coût total estimé : <strong><?= sanitize(formatPrice($cout)) ?></strong>/portion</span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-vg btn-sm">
                            <i class="bi bi-save me-1"></i>Enregistrer la fiche
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ============================= INGRÉDIENTS ============================= -->
<div class="tab-pane fade <?= $activeTab === 'ingredients' ? 'show active' : '' ?>" id="pane-ingredients">

    <!-- Créer un ingrédient -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Nouvel ingrédient</div>
        <div class="card-body">
            <form method="POST" action="/employe/ingredient/creer" class="row g-3">
                <?= csrfField() ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Libellé <span class="text-danger">*</span></label>
                    <input type="text" name="libelle" class="form-control" maxlength="100" required placeholder="ex : Bœuf bourguignon">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Unité</label>
                    <select name="unite" class="form-select">
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="L">L</option>
                        <option value="cL">cL</option>
                        <option value="pièce">pièce</option>
                        <option value="portion">portion</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Prix / unité (€ HT)</label>
                    <input type="number" name="prix_unitaire" class="form-control" step="0.0001" min="0" value="0" required>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Seuil alerte (stock)</label>
                    <input type="number" name="seuil_alerte" class="form-control" step="0.001" min="0" placeholder="optionnel">
                </div>
                <div class="col-12 col-lg-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-vg w-100"><i class="bi bi-plus-circle me-1"></i>Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des ingrédients -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Libellé</th>
                    <th>Unité</th>
                    <th class="text-end">Prix / unité</th>
                    <th class="text-end">Stock courant</th>
                    <th>Seuil alerte</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$ingredients): ?>
                <tr><td colspan="6" class="text-muted text-center py-4">Aucun ingrédient.</td></tr>
            <?php else: ?>
            <?php foreach ($ingredients as $ing): ?>
            <tr>
                <td class="fw-semibold"><?= sanitize($ing['libelle']) ?></td>
                <td><?= sanitize($ing['unite']) ?></td>
                <td class="text-end"><?= sanitize(formatPrice($ing['prix_unitaire'])) ?></td>
                <td class="text-end <?= (float)$ing['stock_courant'] < 0 ? 'text-danger fw-bold' : '' ?>">
                    <?= sanitize(formatPriceInput($ing['stock_courant'])) ?> <?= sanitize($ing['unite']) ?>
                    <?php if ($ing['seuil_alerte'] !== null && (float)$ing['stock_courant'] < (float)$ing['seuil_alerte']): ?>
                        <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Sous le seuil d'alerte"></i>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= $ing['seuil_alerte'] !== null ? sanitize(formatPriceInput($ing['seuil_alerte'])) . ' ' . sanitize($ing['unite']) : '—' ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary me-1"
                        data-bs-toggle="modal" data-bs-target="#modal-edit-ingredient"
                        data-id="<?= (int)$ing['ingredient_id'] ?>"
                        data-libelle="<?= sanitize($ing['libelle']) ?>"
                        data-unite="<?= sanitize($ing['unite']) ?>"
                        data-prix="<?= sanitize(formatPriceInput($ing['prix_unitaire'])) ?>"
                        data-seuil="<?= $ing['seuil_alerte'] !== null ? sanitize(formatPriceInput($ing['seuil_alerte'])) : '' ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="/employe/ingredient/supprimer" class="d-inline" data-confirm="Supprimer cet ingrédient ? Les fiches techniques liées seront affectées.">
                        <?= csrfField() ?>
                        <input type="hidden" name="ingredient_id" value="<?= (int)$ing['ingredient_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal modifier ingrédient -->
    <div class="modal fade" id="modal-edit-ingredient" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Modifier l'ingrédient</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="/employe/ingredient/modifier">
                    <?= csrfField() ?>
                    <div class="modal-body row g-3">
                        <input type="hidden" name="ingredient_id" id="edit-ing-id">
                        <div class="col-12">
                            <label class="form-label">Libellé *</label>
                            <input type="text" name="libelle" id="edit-ing-libelle" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unité</label>
                            <select name="unite" id="edit-ing-unite" class="form-select">
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="L">L</option>
                                <option value="cL">cL</option>
                                <option value="pièce">pièce</option>
                                <option value="portion">portion</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Prix / unité (€ HT)</label>
                            <input type="number" name="prix_unitaire" id="edit-ing-prix" class="form-control" step="0.0001" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Seuil alerte</label>
                            <input type="number" name="seuil_alerte" id="edit-ing-seuil" class="form-control" step="0.001" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-vg">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================= STOCKS ============================= -->
<div class="tab-pane fade <?= $activeTab === 'stocks' ? 'show active' : '' ?>" id="pane-stocks">

    <!-- Ajouter un mouvement -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Enregistrer un mouvement</div>
        <div class="card-body">
            <form method="POST" action="/employe/stock/mouvement/ajouter" class="row g-3">
                <?= csrfField() ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Ingrédient <span class="text-danger">*</span></label>
                    <select name="ingredient_id" class="form-select" required>
                        <option value="">— choisir —</option>
                        <?php foreach ($ingredients as $ing): ?>
                        <option value="<?= (int)$ing['ingredient_id'] ?>"><?= sanitize($ing['libelle']) ?> (<?= sanitize($ing['unite']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Type</label>
                    <select name="type_mouvement" class="form-select">
                        <option value="entree">Entrée (réception)</option>
                        <option value="sortie">Sortie (consommation)</option>
                        <option value="ajustement">Ajustement (inventaire)</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Quantité <span class="text-danger">*</span></label>
                    <input type="number" name="quantite" class="form-control" step="0.001" min="0.001" required>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label">Motif</label>
                    <input type="text" name="motif" class="form-control" maxlength="200" placeholder="optionnel">
                </div>
                <div class="col-12 col-lg-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-vg w-100"><i class="bi bi-plus-circle"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Historique des mouvements -->
    <h2 class="h5 mb-3">Historique des mouvements</h2>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Ingrédient</th>
                    <th>Type</th>
                    <th class="text-end">Qté</th>
                    <th>Motif</th>
                    <th>Par</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$mouvements): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">Aucun mouvement enregistré.</td></tr>
            <?php else: ?>
            <?php foreach ($mouvements as $m): ?>
            <tr>
                <td class="text-muted small text-nowrap"><?= sanitize(formatDateTimeFr($m['created_at'])) ?></td>
                <td class="fw-semibold"><?= sanitize($m['ingredient']) ?></td>
                <td>
                    <?php if ($m['type_mouvement'] === 'entree'): ?>
                        <span class="badge bg-success">Entrée</span>
                    <?php elseif ($m['type_mouvement'] === 'sortie'): ?>
                        <span class="badge bg-danger">Sortie</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Ajustement</span>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= sanitize(formatPriceInput($m['quantite'])) ?> <?= sanitize($m['unite']) ?></td>
                <td class="text-muted small"><?= sanitize($m['motif'] ?? '—') ?></td>
                <td class="text-muted small"><?= sanitize(trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? ''))) ?: '—' ?></td>
                <td>
                    <form method="POST" action="/employe/stock/mouvement/supprimer" class="d-inline" data-confirm="Supprimer ce mouvement de stock ?">
                        <?= csrfField() ?>
                        <input type="hidden" name="mouvement_id" value="<?= (int)$m['mouvement_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- /.tab-content -->

<script nonce="<?= cspNonce() ?>">
(function () {
    // Modal modifier ingrédient
    var modal = document.getElementById('modal-edit-ingredient');
    if (modal) {
        modal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            modal.querySelector('#edit-ing-id').value     = btn.dataset.id;
            modal.querySelector('#edit-ing-libelle').value= btn.dataset.libelle;
            modal.querySelector('#edit-ing-prix').value   = btn.dataset.prix;
            modal.querySelector('#edit-ing-seuil').value  = btn.dataset.seuil;
            var sel = modal.querySelector('#edit-ing-unite');
            for (var opt of sel.options) { opt.selected = opt.value === btn.dataset.unite; }
        });
    }

    // Ajouter/supprimer lignes de recette dynamiquement
    document.querySelectorAll('.btn-add-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tableId = btn.dataset.target;
            var ingredients = JSON.parse(btn.dataset.ingredients || '[]');
            var tbody = document.querySelector('#' + tableId + ' tbody');
            var opts = ingredients.map(function (i) {
                return '<option value="' + i.id + '">' + i.libelle + ' (' + i.unite + ')</option>';
            }).join('');
            var row = document.createElement('tr');
            row.innerHTML = '<td><select name="ingredient_id[]" class="form-select form-select-sm" required>'
                + '<option value="">— choisir —</option>' + opts + '</select></td>'
                + '<td><input type="number" name="grammage[]" class="form-control form-control-sm" step="0.001" min="0.001" required></td>'
                + '<td class="text-muted small">—</td><td></td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-trash"></i></button></td>';
            tbody.appendChild(row);
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-row')) {
            e.target.closest('tr').remove();
        }
    });

    // Tab actif depuis ?tab=
    var tabParam = new URLSearchParams(window.location.search).get('tab');
    if (tabParam) {
        var target = document.querySelector('[data-bs-target="#pane-' + tabParam + '"]');
        if (target) { new bootstrap.Tab(target).show(); }
    }
}());
</script>
