<?php $pageTitle = buildPageTitle('Conditions générales de vente'); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <nav aria-label="Fil d'Ariane">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Accueil</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Conditions générales de vente</li>
                </ol>
            </nav>
            <h1 class="fw-bold mb-4">Conditions générales de vente</h1>

            <?php if (is_array($termsDocument)): ?>
                <section class="mb-5" aria-labelledby="cgv-vendeur">
                    <h2 class="h4" id="cgv-vendeur">Vendeur</h2>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($termsDocument['seller'] as $line): ?>
                            <li><?= sanitize($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <?php foreach ($termsDocument['sections'] as $index => $section): ?>
                    <section class="mb-4" aria-labelledby="cgv-section-<?= (int) $index ?>">
                        <h2 class="h4" id="cgv-section-<?= (int) $index ?>"><?= sanitize($section['title']) ?></h2>
                        <?php foreach ($section['paragraphs'] as $paragraph): ?>
                            <p><?= sanitize($paragraph) ?></p>
                        <?php endforeach; ?>
                        <?php if ($section['items'] !== []): ?>
                            <ul>
                                <?php foreach ($section['items'] as $item): ?>
                                    <li><?= sanitize($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php $customTerms = is_array($termsDocument) ? $termsDocument['explicit_content'] : $explicitContent; ?>
            <?php if (is_string($customTerms) && trim($customTerms) !== ''): ?>
                <section class="legal-custom-content mb-4" aria-labelledby="cgv-complementaires">
                    <?php if (is_array($termsDocument)): ?>
                        <h2 class="h4" id="cgv-complementaires">Dispositions complémentaires</h2>
                    <?php endif; ?>
                    <div><?= nl2br(sanitize($customTerms)) ?></div>
                </section>
            <?php elseif (!is_array($termsDocument)): ?>
                <div class="alert alert-warning" role="status">
                    Les conditions générales de vente ne sont pas encore configurées.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
