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
            <?php if (!empty($cgvContenu)): ?>
                <div class="legal-custom-content"><?= nl2br(sanitize($cgvContenu)) ?></div>
            <?php else: ?>
                <div class="alert alert-warning" role="status">
                    Les conditions générales de vente ne sont pas encore configurées.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
