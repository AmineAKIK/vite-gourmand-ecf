<?php
// src/views/pages/employe/menus.php
$pageTitle = 'Gestion des menus - Vite & Gourmand';
$dashboardUrl = hasRole('administrateur') ? '/admin' : '/employe';

/* Regroupe les plats par catégorie pour les checkboxes */
$platsByCategorie = [];
foreach ($plats as $plat) {
    $platsByCategorie[$plat['categorie']][] = $plat;
}
?>
<div class="container py-5">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= $dashboardUrl ?>" class="btn btn-outline-secondary btn-sm" aria-label="Retour au tableau de bord">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
            <h1 class="h3 fw-bold mb-0">
                <i class="bi bi-journal-text me-2 text-vg"></i>Gestion des menus
            </h1>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalCreerPlat"
                aria-label="Ajouter un plat">
                <i class="bi bi-plus-lg me-1"></i>Ajouter un plat
            </button>
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalCreerMenu"
                aria-label="Ajouter un menu">
                <i class="bi bi-plus-lg me-1"></i>Ajouter un menu
            </button>
        </div>
    </div>

    <!-- Tableau des menus -->
    <?php if (empty($menus)): ?>
        <div class="alert alert-info">Aucun menu enregistré.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="Liste des menus">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Titre</th>
                        <th scope="col">Thème</th>
                        <th scope="col">Régime</th>
                        <th scope="col">Min pers.</th>
                        <th scope="col">Prix / pers.</th>
                        <th scope="col">Stock</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menus as $menu): ?>
                    <tr>
                        <td class="fw-semibold"><?= sanitize($menu['titre'] ?? '') ?></td>
                        <td><?= sanitize($menu['theme']  ?? '—') ?></td>
                        <td><?= sanitize($menu['regime'] ?? '—') ?></td>
                        <td><?= (int)($menu['nombre_personne_minimum'] ?? 0) ?></td>
                        <td>
                            <span class="prix-tag"><?= number_format((float)($menu['prix_par_personne'] ?? 0), 2, ',', ' ') ?> €</span>
                        </td>
                        <td>
                            <?php $stock = $menu['quantite_restante']; ?>
                            <?php if ($stock === null): ?>
                                <span class="badge bg-secondary">Illimité</span>
                            <?php else: ?>
                                <span class="badge <?= (int)$stock > 0 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= (int)$stock ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button
                                class="btn btn-sm btn-outline-primary me-1"
                                data-bs-toggle="modal"
                                data-bs-target="#modalModifierMenu<?= (int)$menu['menu_id'] ?>"
                                aria-label="Modifier le menu <?= sanitize($menu['titre'] ?? '') ?>"
                            ><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="/employe/menu/supprimer" class="d-inline form-confirm">
                                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                                <input type="hidden" name="menu_id" value="<?= (int)$menu['menu_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    aria-label="Supprimer le menu <?= sanitize($menu['titre'] ?? '') ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Tableau des plats -->
    <h2 class="h4 fw-bold mt-5 mb-3">Gestion des plats</h2>
    <?php if (empty($plats)): ?>
        <div class="alert alert-info">Aucun plat enregistré.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="Liste des plats">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Titre</th>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Description</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plats as $plat): ?>
                    <tr>
                        <td class="fw-semibold"><?= sanitize($plat['titre']) ?></td>
                        <td><?= sanitize($plat['categorie']) ?></td>
                        <td><?= sanitize($plat['description'] ?? '') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary me-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalModifPlat<?= (int)$plat['plat_id'] ?>"
                                    aria-label="Modifier le plat <?= sanitize($plat['titre']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/employe/plat/supprimer" class="d-inline form-confirm">
                                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                                <input type="hidden" name="plat_id" value="<?= (int)$plat['plat_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        aria-label="Supprimer le plat <?= sanitize($plat['titre']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php foreach ($plats as $plat): ?>
    <div class="modal fade" id="modalModifPlat<?= (int)$plat['plat_id'] ?>" tabindex="-1"
         aria-labelledby="modalModifPlatLabel<?= (int)$plat['plat_id'] ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalModifPlatLabel<?= (int)$plat['plat_id'] ?>">
                        Modifier : <?= sanitize($plat['titre']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" action="/employe/plat/modifier" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                    <input type="hidden" name="plat_id" value="<?= (int)$plat['plat_id'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="titre_plat_<?= (int)$plat['plat_id'] ?>" class="form-label">Titre</label>
                            <input type="text" class="form-control"
                                   id="titre_plat_<?= (int)$plat['plat_id'] ?>"
                                   name="titre" value="<?= sanitize($plat['titre']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="desc_plat_<?= (int)$plat['plat_id'] ?>" class="form-label">Description</label>
                            <textarea class="form-control"
                                      id="desc_plat_<?= (int)$plat['plat_id'] ?>"
                                      name="description" rows="2"><?= sanitize($plat['description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="cat_plat_<?= (int)$plat['plat_id'] ?>" class="form-label">Catégorie</label>
                            <select class="form-select"
                                    id="cat_plat_<?= (int)$plat['plat_id'] ?>"
                                    name="categorie_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['categorie_id'] ?>"
                                        <?= $cat['libelle'] === $plat['categorie'] ? 'selected' : '' ?>>
                                        <?= sanitize($cat['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="photo_plat_<?= (int)$plat['plat_id'] ?>" class="form-label">Remplacer la photo</label>
                            <input type="file" class="form-control"
                                   id="photo_plat_<?= (int)$plat['plat_id'] ?>"
                                   name="photo"
                                   accept="image/jpeg,image/png,image/webp">
                        </div>
                        <?php if (!empty($allergenes)): ?>
                        <?php $allergenesPlat = array_filter(array_map('intval', explode(',', (string)($plat['allergene_ids'] ?? '')))); ?>
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Allergènes</legend>
                            <div class="row g-1">
                                <?php foreach ($allergenes as $al): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="allergenes[]"
                                               value="<?= (int)$al['allergene_id'] ?>"
                                               id="modif-al-<?= (int)$plat['plat_id'] ?>-<?= (int)$al['allergene_id'] ?>"
                                               <?= in_array((int)$al['allergene_id'], $allergenesPlat, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small"
                                               for="modif-al-<?= (int)$plat['plat_id'] ?>-<?= (int)$al['allergene_id'] ?>">
                                            <?= sanitize($al['libelle']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-vg">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- ============================================================
     MODAL : Créer un menu
     ============================================================ -->
<div class="modal fade" id="modalCreerMenu" tabindex="-1"
     aria-labelledby="modalCreerMenuLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="modalCreerMenuLabel">
                    <i class="bi bi-plus-circle me-2 text-vg"></i>Ajouter un menu
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="POST" action="/employe/menu/creer" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="creer-titre" class="form-label">Titre <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="creer-titre" name="titre"
                                required aria-required="true" maxlength="200">
                        </div>
                        <div class="col-12">
                            <label for="creer-description" class="form-label">Description</label>
                            <textarea class="form-control" id="creer-description" name="description"
                                rows="3" maxlength="1000"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="creer-min-pers" class="form-label">Nb personnes minimum <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="creer-min-pers"
                                name="nombre_personne_minimum" min="1" required aria-required="true">
                        </div>
                        <div class="col-md-4">
                            <label for="creer-prix" class="form-label">Prix / personne (€) <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="creer-prix"
                                name="prix_par_personne" min="0" step="0.01" required aria-required="true">
                        </div>
                        <div class="col-md-4">
                            <label for="creer-stock" class="form-label">Quantité restante <small class="text-muted">(vide = illimité)</small></label>
                            <input type="number" class="form-control" id="creer-stock"
                                name="quantite_restante" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="creer-theme" class="form-label">Thème</label>
                            <select class="form-select" id="creer-theme" name="theme_id">
                                <option value="">— Aucun thème —</option>
                                <?php foreach ($themes as $t): ?>
                                    <option value="<?= (int)$t['theme_id'] ?>"><?= sanitize($t['nom'] ?? $t['libelle'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="creer-regime" class="form-label">Régime alimentaire</label>
                            <select class="form-select" id="creer-regime" name="regime_id">
                                <option value="">— Aucun régime —</option>
                                <?php foreach ($regimes as $r): ?>
                                    <option value="<?= (int)$r['regime_id'] ?>"><?= sanitize($r['nom'] ?? $r['libelle'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="creer-conditions" class="form-label">Conditions particulières</label>
                            <textarea class="form-control" id="creer-conditions" name="conditions"
                                rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="creer-images" class="form-label">Photos du menu (plusieurs possibles)</label>
                            <input type="file" class="form-control" id="creer-images" name="images[]"
                                   multiple accept="image/jpeg,image/png,image/webp"
                                   aria-label="Galerie d'images du menu">
                            <div class="form-text">Formats acceptés : JPG, PNG, WEBP</div>
                        </div>

                        <!-- Sélection des plats groupés par catégorie -->
                        <?php if (!empty($platsByCategorie)): ?>
                        <fieldset class="col-12">
                            <legend class="form-label fw-semibold fs-6">Plats composant ce menu</legend>
                            <div class="row g-2">
                                <?php foreach ($platsByCategorie as $categorie => $platsGroupe): ?>
                                <div class="col-md-4">
                                    <p class="fw-semibold small text-vg mb-1"><?= sanitize($categorie) ?></p>
                                    <?php foreach ($platsGroupe as $plat): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            name="plats[]"
                                            value="<?= (int)$plat['plat_id'] ?>"
                                            id="creer-plat-<?= (int)$plat['plat_id'] ?>">
                                        <label class="form-check-label small"
                                            for="creer-plat-<?= (int)$plat['plat_id'] ?>">
                                            <?= sanitize($plat['titre']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL : Créer un plat
     ============================================================ -->
<div class="modal fade" id="modalCreerPlat" tabindex="-1"
     aria-labelledby="modalCreerPlatLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="modalCreerPlatLabel">
                    <i class="bi bi-egg-fried me-2 text-vg"></i>Ajouter un plat
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="POST" action="/employe/plat/creer" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="plat-titre" class="form-label">Titre du plat <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="plat-titre" name="titre"
                            required aria-required="true" maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label for="plat-description" class="form-label">Description</label>
                        <textarea class="form-control" id="plat-description" name="description"
                            rows="3" maxlength="500"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="plat-categorie" class="form-label">Catégorie <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select" id="plat-categorie" name="categorie_id"
                            required aria-required="true">
                            <option value="">— Choisir une catégorie —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['categorie_id'] ?>"><?= sanitize($cat['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="plat-photo" class="form-label">Photo du plat</label>
                        <input type="file" class="form-control" id="plat-photo" name="photo"
                               accept="image/jpeg,image/png,image/webp"
                               aria-label="Photo du plat">
                    </div>
                    <?php if (!empty($allergenes)): ?>
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">Allergènes</legend>
                        <div class="row g-1">
                            <?php foreach ($allergenes as $al): ?>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                        name="allergenes[]"
                                        value="<?= (int)$al['allergene_id'] ?>"
                                        id="al-<?= (int)$al['allergene_id'] ?>">
                                    <label class="form-check-label small" for="al-<?= (int)$al['allergene_id'] ?>">
                                        <?= sanitize($al['libelle']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer le plat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     MODALS : Modifier chaque menu
     ============================================================ -->
<?php foreach ($menus as $menu):
    $platsMenu = $platsByMenu[(int)$menu['menu_id']] ?? [];
?>
<div class="modal fade" id="modalModifierMenu<?= (int)$menu['menu_id'] ?>" tabindex="-1"
     aria-labelledby="modalModifLabel<?= (int)$menu['menu_id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="modalModifLabel<?= (int)$menu['menu_id'] ?>">
                    <i class="bi bi-pencil me-2 text-vg"></i>Modifier — <?= sanitize($menu['titre'] ?? '') ?>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="POST" action="/employe/menu/modifier" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="menu_id" value="<?= (int)$menu['menu_id'] ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="modif-titre-<?= (int)$menu['menu_id'] ?>" class="form-label">Titre <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control"
                                id="modif-titre-<?= (int)$menu['menu_id'] ?>"
                                name="titre"
                                value="<?= sanitize($menu['titre'] ?? '') ?>"
                                required aria-required="true" maxlength="200">
                        </div>
                        <div class="col-12">
                            <label for="modif-desc-<?= (int)$menu['menu_id'] ?>" class="form-label">Description</label>
                            <textarea class="form-control"
                                id="modif-desc-<?= (int)$menu['menu_id'] ?>"
                                name="description" rows="3" maxlength="1000"
                            ><?= sanitize($menu['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="modif-min-<?= (int)$menu['menu_id'] ?>" class="form-label">Nb personnes minimum</label>
                            <input type="number" class="form-control"
                                id="modif-min-<?= (int)$menu['menu_id'] ?>"
                                name="nombre_personne_minimum" min="1"
                                value="<?= (int)($menu['nombre_personne_minimum'] ?? 1) ?>"
                                required aria-required="true">
                        </div>
                        <div class="col-md-4">
                            <label for="modif-prix-<?= (int)$menu['menu_id'] ?>" class="form-label">Prix / personne (€)</label>
                            <input type="number" class="form-control"
                                id="modif-prix-<?= (int)$menu['menu_id'] ?>"
                                name="prix_par_personne" min="0" step="0.01"
                                value="<?= number_format((float)($menu['prix_par_personne'] ?? 0), 2, '.', '') ?>"
                                required aria-required="true">
                        </div>
                        <div class="col-md-4">
                            <label for="modif-stock-<?= (int)$menu['menu_id'] ?>" class="form-label">Quantité restante <small class="text-muted">(vide = illimité)</small></label>
                            <input type="number" class="form-control"
                                id="modif-stock-<?= (int)$menu['menu_id'] ?>"
                                name="quantite_restante" min="0"
                                value="<?= $menu['quantite_restante'] !== null ? (int)$menu['quantite_restante'] : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="modif-theme-<?= (int)$menu['menu_id'] ?>" class="form-label">Thème</label>
                            <select class="form-select" id="modif-theme-<?= (int)$menu['menu_id'] ?>" name="theme_id">
                                <option value="">— Aucun thème —</option>
                                <?php foreach ($themes as $t): ?>
                                    <option value="<?= (int)$t['theme_id'] ?>"
                                        <?= (int)($menu['theme_id'] ?? 0) === (int)$t['theme_id'] ? 'selected' : '' ?>>
                                        <?= sanitize($t['nom'] ?? $t['libelle'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modif-regime-<?= (int)$menu['menu_id'] ?>" class="form-label">Régime alimentaire</label>
                            <select class="form-select" id="modif-regime-<?= (int)$menu['menu_id'] ?>" name="regime_id">
                                <option value="">— Aucun régime —</option>
                                <?php foreach ($regimes as $r): ?>
                                    <option value="<?= (int)$r['regime_id'] ?>"
                                        <?= (int)($menu['regime_id'] ?? 0) === (int)$r['regime_id'] ? 'selected' : '' ?>>
                                        <?= sanitize($r['nom'] ?? $r['libelle'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="modif-cond-<?= (int)$menu['menu_id'] ?>" class="form-label">Conditions particulières</label>
                            <textarea class="form-control"
                                id="modif-cond-<?= (int)$menu['menu_id'] ?>"
                                name="conditions" rows="2" maxlength="500"
                            ><?= sanitize($menu['conditions'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="modif-images-<?= (int)$menu['menu_id'] ?>" class="form-label">Ajouter des photos</label>
                            <input type="file" class="form-control" id="modif-images-<?= (int)$menu['menu_id'] ?>" name="images[]"
                                   multiple accept="image/jpeg,image/png,image/webp"
                                   aria-label="Galerie d'images du menu">
                            <div class="form-text">Formats acceptés : JPG, PNG, WEBP</div>
                            <?php
                            $db = \Database::getConnection();
                            $stmtImg = $db->prepare("SELECT * FROM menu_image WHERE menu_id=? ORDER BY ordre");
                            $stmtImg->execute([(int)$menu['menu_id']]);
                            $imagesMenu = $stmtImg->fetchAll();
                            if (!empty($imagesMenu)): ?>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($imagesMenu as $img): ?>
                                <div class="d-flex align-items-center gap-1">
                                    <img src="/<?= sanitize($img['chemin']) ?>"
                                         width="50" height="50"
                                         style="object-fit:cover;border-radius:4px"
                                         alt="Image menu">
                                    <form method="POST" action="/employe/menu/image/supprimer" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                                        <input type="hidden" name="image_id" value="<?= (int)$img['image_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-1"
                                                aria-label="Supprimer cette image"
                                                onclick="return confirm('Supprimer cette image ?')">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Plats du menu -->
                        <?php if (!empty($platsByCategorie)): ?>
                        <fieldset class="col-12">
                            <legend class="form-label fw-semibold fs-6">Plats composant ce menu</legend>
                            <div class="row g-2">
                                <?php foreach ($platsByCategorie as $categorie => $platsGroupe): ?>
                                <div class="col-md-4">
                                    <p class="fw-semibold small text-vg mb-1"><?= sanitize($categorie) ?></p>
                                    <?php foreach ($platsGroupe as $plat): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            name="plats[]"
                                            value="<?= (int)$plat['plat_id'] ?>"
                                            id="modif-plat-<?= (int)$menu['menu_id'] ?>-<?= (int)$plat['plat_id'] ?>"
                                            <?= in_array((int)$plat['plat_id'], $platsMenu, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small"
                                            for="modif-plat-<?= (int)$menu['menu_id'] ?>-<?= (int)$plat['plat_id'] ?>">
                                            <?= sanitize($plat['titre']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
