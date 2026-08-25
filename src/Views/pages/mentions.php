<?php $pageTitle = buildPageTitle('Mentions légales'); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <nav aria-label="Fil d'Ariane">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Accueil</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mentions légales</li>
                </ol>
            </nav>
            <h1 class="fw-bold mb-4">Mentions légales</h1>
            <?php if (!empty($mentionsContenu)): ?>
                <div class="legal-custom-content"><?= nl2br(sanitize($mentionsContenu)) ?></div>
            <?php else: ?>
                <div class="alert alert-warning" role="status">Les mentions légales ne sont pas encore configurées.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
