<?php
// src/views/pages/employe/menus.php
$pageTitle = 'Gestion des menus - Vite & Gourmand';

/* Regroupe les plats par catégorie pour les checkboxes */
$platsByCategorie = [];
foreach ($plats as $plat) {
    $platsByCategorie[$plat['categorie']][] = $plat;
}
?>
<div class="container py-5">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="h3 fw-bold mb-0">
            <i class="bi bi-journal-text me-2 text-vg"></i>Gestion des menus
        </h1>
        <div class="d-flex gap-2">
            <button class="btn btn-vg-outline" data-bs-toggle="modal" data-bs-target="#modalCreerPlat"
                aria-label="Ajouter un plat">
                <i class="bi bi-plus-lg me-1"></i>Ajouter un plat
            </button>
            <button class="btn btn-vg-outline" data-bs-toggle="modal" data-bs-target="#modalCreerMenu"
                aria-label="Ajouter un menu">
                <i class="bi bi-plus-lg me-1"></i>Ajouter un menu
            </button>
        </div>
    </div>

    <!-- Tableau des menus -->
    <?php if (empty($menus)): ?>
        <div class="alert alert-info">Aucun menu enregistré.</div>
    <?php else: ?>
        <div class="card shadow-sm mb-0" style="border:1px solid rgba(0,0,0,.08);">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" aria-label="Liste des menus">
                    <thead>
                        <tr style="background:rgba(0,0,0,.03); border-bottom:1px solid rgba(0,0,0,.08);">
                            <th scope="col" class="ps-3 text-vg fw-semibold">Titre</th>
                            <th scope="col" class="text-vg fw-semibold">Thème</th>
                            <th scope="col" class="text-vg fw-semibold">Régime</th>
                            <th scope="col" class="text-vg fw-semibold">Min pers.</th>
                            <th scope="col" class="text-vg fw-semibold">Prix / pers.</th>
                            <th scope="col" class="text-vg fw-semibold">Stock</th>
                            <th scope="col" class="text-vg fw-semibold pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menus as $menu): ?>
                        <tr>
                            <td class="fw-semibold ps-3"><?= sanitize($menu['titre'] ?? '') ?></td>
                            <td class="text-muted"><?= sanitize($menu['theme']  ?? '—') ?></td>
                            <td class="text-muted"><?= sanitize($menu['regime'] ?? '—') ?></td>
                            <td class="text-muted"><?= (int)($menu['nombre_personne_minimum'] ?? 0) ?></td>
                            <td>
                                <span class="fw-semibold text-vg"><?= sanitize(formatPrice($menu['prix_par_personne'] ?? 0)) ?></span>
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
                            <td class="pe-3">
                                <button
                                    class="btn btn-sm btn-vg-outline me-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalModifierMenu<?= (int)$menu['menu_id'] ?>"
                                    aria-label="Modifier le menu <?= sanitize($menu['titre'] ?? '') ?>"
                                ><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="/employe/menu/supprimer" class="d-inline form-confirm">
                                    <?= csrfField() ?>
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
        </div>
    <?php endif; ?>

    <!-- Plats groupés par catégorie -->
    <h2 class="h4 fw-bold mt-5 mb-3">Gestion des plats</h2>
    <?php if (empty($plats)): ?>
        <div class="alert alert-info">Aucun plat enregistré.</div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($platsByCategorie as $categorie => $platsGroupe): ?>
            <div class="card shadow-sm" style="border:1px solid rgba(0,0,0,.08);">
                <div class="card-header d-flex align-items-center justify-content-between py-2" style="background:rgba(0,0,0,.03); border-bottom:1px solid rgba(0,0,0,.08);">
                    <span class="fw-semibold text-vg"><?= sanitize($categorie) ?></span>
                    <span class="badge bg-secondary fw-normal"><?= count($platsGroupe) ?> plat<?= count($platsGroupe) > 1 ? 's' : '' ?></span>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($platsGroupe as $plat): ?>
                    <li class="list-group-item d-flex align-items-center justify-content-between gap-3 py-2 px-3">
                        <div class="flex-grow-1">
                            <div class="fw-medium"><?= sanitize($plat['titre']) ?></div>
                            <?php if (!empty($plat['allergenes'])): ?>
                                <small class="text-muted">Allergènes : <?= sanitize($plat['allergenes']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <button class="btn btn-sm btn-vg-outline"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalModifPlat<?= (int)$plat['plat_id'] ?>"
                                    aria-label="Modifier <?= sanitize($plat['titre']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/employe/plat/supprimer" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="plat_id" value="<?= (int)$plat['plat_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Supprimer le plat « <?= sanitize($plat['titre']) ?> » ?"
                                        aria-label="Supprimer <?= sanitize($plat['titre']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
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
                <form method="POST" action="/employe/plat/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="plat_id" value="<?= (int)$plat['plat_id'] ?>">
                    <div class="modal-body">
                        <div id="modifier_plat_<?= (int)$plat['plat_id'] ?>-error" class="alert alert-danger d-none" role="alert"></div>
                        <div class="mb-3">
                            <label for="titre_plat_<?= (int)$plat['plat_id'] ?>" class="form-label">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control"
                                   id="titre_plat_<?= (int)$plat['plat_id'] ?>"
                                   name="titre" value="<?= sanitize($plat['titre']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="cat_plat_<?= (int)$plat['plat_id'] ?>" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="cat_plat_<?= (int)$plat['plat_id'] ?>" name="categorie_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['categorie_id'] ?>" <?= $cat['libelle'] === $plat['categorie'] ? 'selected' : '' ?>>
                                        <?= sanitize($cat['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="al_plat_<?= (int)$plat['plat_id'] ?>" class="form-label">Allergènes</label>
                            <input type="text" class="form-control tag-input"
                                   id="al_plat_<?= (int)$plat['plat_id'] ?>"
                                   name="allergenes"
                                   value="<?= sanitize($plat['allergenes'] ?? '') ?>"
                                   placeholder="ex : Gluten, Lait, Œufs">
                            <div class="form-text">Séparez les allergènes par des virgules.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-vg-outline" data-bs-dismiss="modal">Annuler</button>
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
                <?= csrfField() ?>
                <div class="modal-body">
                    <div id="creer_menu-error" class="alert alert-danger d-none" role="alert"></div>
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
                            <label for="creer-images" class="form-label">Photos du menu <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="file" class="form-control image-picker" id="creer-images" name="images[]"
                                   multiple accept="<?= sanitize(MenuAdminService::acceptedImageMimeTypes()) ?>"
                                   required aria-required="true"
                                   aria-label="Galerie d'images du menu (obligatoire)">
                            <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?> — Au moins une photo obligatoire</div>
                            <div class="image-preview-container d-flex flex-wrap gap-2 mt-2"></div>
                        </div>

                        <?php partial('partials/menu_plat_checkboxes', [
                            'platsByCategorie' => $platsByCategorie,
                            'selectedPlatIds' => [],
                            'idPrefix' => 'creer-plat',
                        ]); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-vg-outline" data-bs-dismiss="modal">Annuler</button>
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
            <form method="POST" action="/employe/plat/creer" novalidate>
                <?= csrfField() ?>
                <div class="modal-body">
                    <div id="creer_plat-error" class="alert alert-danger d-none" role="alert"></div>
                    <div class="mb-3">
                        <label for="plat-titre" class="form-label">Titre du plat <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="plat-titre" name="titre"
                            required aria-required="true" maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label for="plat-categorie" class="form-label">Catégorie <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select" id="plat-categorie" name="categorie_id" required aria-required="true">
                            <option value="">— Choisir une catégorie —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['categorie_id'] ?>"><?= sanitize($cat['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="plat-allergenes" class="form-label">Allergènes</label>
                        <input type="text" class="form-control tag-input" id="plat-allergenes" name="allergenes"
                               placeholder="ex : Gluten, Lait, Œufs">
                        <div class="form-text">Séparez les allergènes par des virgules.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-vg-outline" data-bs-dismiss="modal">Annuler</button>
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
                <?= csrfField() ?>
                <input type="hidden" name="menu_id" value="<?= (int)$menu['menu_id'] ?>">
                <div class="modal-body">
                    <div id="modifier_menu_<?= (int)$menu['menu_id'] ?>-error" class="alert alert-danger d-none" role="alert"></div>
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
                                value="<?= sanitize(formatPriceInput($menu['prix_par_personne'] ?? 0)) ?>"
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
                            <input type="file" class="form-control image-picker" id="modif-images-<?= (int)$menu['menu_id'] ?>" name="images[]"
                                   multiple accept="<?= sanitize(MenuAdminService::acceptedImageMimeTypes()) ?>"
                                   aria-label="Galerie d'images du menu">
                            <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?></div>
                            <div class="image-preview-container d-flex flex-wrap gap-2 mt-2"></div>
                            <?php $imagesMenu = $imagesByMenu[(int)$menu['menu_id']] ?? []; ?>
                            <?php if (!empty($imagesMenu)): ?>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($imagesMenu as $img): ?>
                                <div class="d-flex align-items-center gap-1">
                                    <img src="<?= sanitize(imageUrl($img['chemin'])) ?>"
                                         width="50" height="50"
                                         style="object-fit:cover;border-radius:4px"
                                         alt="Image menu">
                                    <form method="POST" action="/employe/menu/image/supprimer" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="image_id" value="<?= (int)$img['image_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-1"
                                                aria-label="Supprimer cette image"
                                                data-confirm="Supprimer cette image ?">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php partial('partials/menu_plat_checkboxes', [
                            'platsByCategorie' => $platsByCategorie,
                            'selectedPlatIds' => $platsMenu,
                            'idPrefix' => 'modif-plat-' . (int)$menu['menu_id'],
                        ]); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-vg-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
