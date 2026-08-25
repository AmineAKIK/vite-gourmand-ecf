<?php $pageTitle = buildPageTitle(); ?>

<section class="hero hero-home text-center" aria-label="Présentation de l'entreprise">
    <?php if (!empty($heroUrl)): ?>
        <img src="<?= sanitize($heroUrl) ?>" class="hero-bg" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
    <?php endif; ?>
    <div class="container hero-content">
        <h1 class="fw-bold mb-3"><?= sanitize(siteName()) ?></h1>
        <?php $subtitle = is_string($heroSousTitre ?? null) && trim($heroSousTitre) !== '' ? $heroSousTitre : siteSlogan(); ?>
        <?php if ($subtitle !== ''): ?><p class="subtitle mb-4"><?= sanitize($subtitle) ?></p><?php endif; ?>
        <?php if (is_string($heroParagraphe ?? null) && trim($heroParagraphe) !== ''): ?>
            <p class="lead text-white-50 mb-0 col-lg-8 mx-auto"><?= nl2br(htmlspecialchars($heroParagraphe, ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($avisValides)): ?>
<section class="py-5 bg-surface-subtle" aria-labelledby="avis-titre">
    <div class="container">
        <h2 id="avis-titre" class="text-center mb-5">Avis clients</h2>
        <div class="row g-4">
            <?php foreach ($avisValides as $avis): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="card h-100 p-3">
                        <div class="card-body">
                            <div class="stars mb-2" aria-label="Note : <?= (int) $avis['note'] ?> sur 5"><?= str_repeat('★', (int) $avis['note']) . str_repeat('☆', 5 - (int) $avis['note']) ?></div>
                            <p class="card-text fst-italic">“<?= htmlspecialchars(html_entity_decode(trim($avis['description'] ?? ''), ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') ?>”</p>
                            <footer class="text-muted small mt-3">
                                <strong><?= sanitize(personFullName($avis)) ?></strong>
                                <?php if (!empty($avis['menu_titre'])): ?> · Menu : <?= sanitize($avis['menu_titre']) ?><?php endif; ?>
                            </footer>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
