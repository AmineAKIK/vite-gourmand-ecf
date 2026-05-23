<?php
// src/views/pages/home.php
$pageTitle = 'Vite & Gourmand - Traiteur à Bordeaux';
?>

<!-- HERO -->
<section class="hero hero-home text-center" aria-label="Présentation de l'entreprise">
    <img
        src="<?= sanitize($heroUrl ?? '/images/hero-traiteur-bordeaux.webp') ?>"
        class="hero-bg"
        alt=""
        aria-hidden="true"
        fetchpriority="high"
        decoding="async"
    >
    <div class="container hero-content">
        <h1 class="fw-bold mb-3">Vite &amp; Gourmand</h1>
        <p class="subtitle mb-4"><?= sanitize($heroSousTitre ?? 'Traiteur bordelais depuis 25 ans') ?></p>
        <p class="lead text-white-50 mb-5 col-md-8 mx-auto">
            Depuis 25 ans, Vite &amp; Gourmand accompagne les particuliers et les professionnels
            avec une cuisine traiteur généreuse, raffinée et préparée à Bordeaux.
        </p>
    </div>
</section>

<!-- ATOUTS -->
<section class="py-5 bg-creme" aria-labelledby="atouts-titre">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5">
                <img
                    src="<?= sanitize($preparationUrl ?? '/images/preparation-traiteur.webp') ?>"
                    class="img-fluid professional-img"
                    alt="Préparation de bouchées traiteur par l'équipe Vite et Gourmand"
                    loading="lazy"
                    decoding="async"
                    width="1000"
                    height="700"
                >
            </div>
            <div class="col-lg-7">
                <h2 id="atouts-titre" class="mb-4">Une équipe professionnelle à votre service</h2>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="bi bi-award display-5 text-vg mb-3"></i>
                            <h3 class="h5">25 ans d'expérience</h3>
                            <p class="text-muted">Julie et José coordonnent chaque prestation avec rigueur, ponctualité et sens du détail.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="bi bi-clipboard-check display-5 text-vg mb-3"></i>
                            <h3 class="h5">Organisation maîtrisée</h3>
                            <p class="text-muted">Les commandes, les délais, les quantités et la livraison sont suivis avec méthode.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="bi bi-stars display-5 text-vg mb-3"></i>
                            <h3 class="h5">Qualité constante</h3>
                            <p class="text-muted">Chaque menu est préparé avec des produits sélectionnés et une présentation soignée.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- AVIS CLIENTS -->
<section class="py-5 bg-creme" aria-labelledby="avis-titre">
    <div class="container">
        <h2 id="avis-titre" class="text-center mb-5">Ils nous font confiance</h2>
        <?php if (empty($avisValides)): ?>
            <div class="alert alert-info text-center mb-0" role="status">
                Les premiers retours clients seront publiés ici très bientôt.
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($avisValides as $avis): ?>
            <div class="col-md-4">
                <article class="card h-100 border-0 shadow-sm p-3" style="background:var(--vg-creme);">
                    <div class="card-body">
                        <div class="stars mb-2" aria-label="Note : <?= (int)$avis['note'] ?> sur 5">
                            <?= str_repeat('★', (int)$avis['note']) . str_repeat('☆', 5 - (int)$avis['note']) ?>
                        </div>
                        <p class="card-text fst-italic">"<?= sanitize($avis['description']) ?>"</p>
                        <footer class="text-muted small mt-3">
                            <strong><?= sanitize(personFullName($avis)) ?></strong>
                            · Menu : <?= sanitize($avis['menu_titre']) ?>
                        </footer>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
