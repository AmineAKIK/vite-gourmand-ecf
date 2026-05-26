<?php $pageTitle = buildPageTitle('Accès refusé'); ?>
<div class="container py-5 text-center">
    <h1 class="fw-bold mb-3">Accès refusé</h1>
    <p class="text-muted mb-4">Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
    <a href="<?= sanitize(roleHomePath()) ?>" class="btn btn-vg">
        Retour à mon espace
    </a>
</div>
