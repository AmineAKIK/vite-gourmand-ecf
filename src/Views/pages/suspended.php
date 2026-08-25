<?php $pageTitle = buildPageTitle('Service indisponible'); ?>
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-6">
            <i class="bi bi-pause-circle display-1 text-warning mb-3 d-block" aria-hidden="true"></i>
            <h1 class="h2 fw-bold mb-2">Service temporairement indisponible</h1>
            <p class="text-muted mb-4">Cette instance n'est pas disponible actuellement.</p>
            <form method="POST" action="/deconnexion" class="d-inline">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-outline-secondary">Se déconnecter</button>
            </form>
        </div>
    </div>
</div>
