<?php
$pageTitle = sanitize($menu['titre']) . ' - Vite & Gourmand';
$prixParPersonne = (float)($menu['prix_par_personne'] ?? 0);
$nombrePersonneMinimum = (int)($menu['nombre_personne_minimum'] ?? 1);
$prixMinimum = $prixParPersonne * $nombrePersonneMinimum;
?>

<div class="container py-5 menus-page menu-detail-page">
    <!-- Fil d'ariane -->
    <nav aria-label="Fil d'Ariane" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Accueil</a></li>
            <li class="breadcrumb-item"><a href="/menus">Menus</a></li>
            <li class="breadcrumb-item active"><?= sanitize($menu['titre']) ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <!-- Galerie -->
        <div class="col-12 col-lg-5">
            <?php if ($menu['images']): ?>
            <div id="galerieMenu" class="carousel slide rounded overflow-hidden shadow" data-bs-ride="carousel" aria-label="Galerie du menu">
                <div class="carousel-inner">
                    <?php foreach ($menu['images'] as $i => $img): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <img src="<?= sanitize(imageUrl($img['chemin'])) ?>" class="d-block w-100" style="height:350px;object-fit:cover" alt="Photo du menu <?= sanitize($menu['titre']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($menu['images']) > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#galerieMenu" data-bs-slide="prev" aria-label="Image précédente">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#galerieMenu" data-bs-slide="next" aria-label="Image suivante">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <img
                    src="/images/menu-placeholder.webp"
                    class="d-block w-100 rounded shadow"
                    style="height:350px;object-fit:cover"
                    alt="Illustration générique du menu <?= sanitize($menu['titre']) ?>"
                    loading="lazy"
                    decoding="async"
                >
            <?php endif; ?>
        </div>

        <!-- Infos principales -->
        <div class="col-12 col-lg-7">
            <div class="mb-2">
                <?php if ($menu['theme']): ?><span class="badge-theme me-1"><?= sanitize($menu['theme']) ?></span><?php endif; ?>
                <?php if ($menu['regime']): ?><span class="badge-regime"><?= sanitize($menu['regime']) ?></span><?php endif; ?>
            </div>
            <h1 class="fw-bold"><?= sanitize($menu['titre']) ?></h1>
            <p class="lead text-muted"><?= nl2br(sanitize($menu['description'] ?? '')) ?></p>

            <div class="menu-pricing-panel my-3" aria-label="Tarif du menu">
                <div class="menu-pricing-main">
                    <span class="menu-pricing-price"><?= sanitize(formatPrice($prixParPersonne)) ?></span>
                    <span class="menu-pricing-unit">par personne</span>
                </div>
                <div class="menu-pricing-divider" aria-hidden="true"></div>
                <div class="menu-pricing-meta">
                    <span class="menu-pricing-minimum"><?= $nombrePersonneMinimum ?> personnes minimum</span>
                    <span class="menu-pricing-total">Commande à partir de <?= sanitize(formatPrice($prixMinimum)) ?></span>
                </div>
            </div>

            <div class="row g-3 my-3">
                <?php if ($menu['quantite_restante'] !== null): ?>
                <div class="col-12">
                    <div class="alert alert-warning py-2 mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Disponibilité limitée :</strong> il reste <?= (int)$menu['quantite_restante'] ?> réservation(s) possible(s) pour ce menu.
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- CONDITIONS BIEN EN ÉVIDENCE -->
            <?php if ($menu['conditions']): ?>
            <div class="alert alert-warning border-warning" role="alert">
                <h2 class="alert-heading h6"><i class="bi bi-info-circle-fill me-2"></i>Conditions importantes</h2>
                <p class="mb-0 small"><?= nl2br(sanitize($menu['conditions'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Ajouter au panier -->
            <?php if (isAuth()): ?>
                <form method="POST" action="/panier/ajouter" class="menu-cart-form mt-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="menu_id" value="<?= (int)$menu['menu_id'] ?>">
                    <div class="input-group mb-2">
                        <label for="detail_nb_personnes" class="input-group-text">Personnes</label>
                        <input type="number" class="form-control" id="detail_nb_personnes"
                               name="nombre_personne"
                               min="<?= $nombrePersonneMinimum ?>"
                               max="500"
                               value="<?= $nombrePersonneMinimum ?>"
                               required>
                    </div>
                    <button type="submit" class="btn btn-vg btn-lg w-100">
                        <i class="bi bi-cart-plus me-2"></i>Ajouter au panier
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-lock me-2"></i>
                    <a href="/connexion">Connectez-vous</a> ou <a href="/inscription">créez un compte</a> pour commander.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Composition du menu -->
    <section class="mt-5" aria-labelledby="composition-titre">
        <h2 id="composition-titre" class="mb-4">Composition du menu</h2>
        <?php
        $categories = ['Entrée' => 'Entrées', 'Plat principal' => 'Plats', 'Dessert' => 'Desserts'];
        foreach ($categories as $cat => $label):
            $plats = array_filter($menu['plats'], fn($p) => $p['categorie'] === $cat);
            if (!$plats) continue;
        ?>
        <div class="mb-4">
            <h3 class="h5 text-vg border-bottom pb-2 mb-3"><?= $label ?></h3>
            <div class="row g-3">
                <?php foreach ($plats as $plat): ?>
                <div class="col-12 col-lg-4">
                    <div class="card menu-panel p-3 h-100">
                        <h4 class="h6 fw-bold"><?= sanitize($plat['titre']) ?></h4>
                        <?php if ($plat['description']): ?>
                            <p class="small text-muted mb-2"><?= sanitize($plat['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($plat['allergenes']): ?>
                            <div class="mt-auto">
                                <small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>
                                    Allergènes : <?= sanitize($plat['allergenes']) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
</div>
