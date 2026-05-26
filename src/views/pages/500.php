<?php
// src/views/pages/500.php
$pageTitle = buildPageTitle('Erreur serveur');
?>
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-6">

            <div class="display-1 fw-bold text-vg mb-2" aria-hidden="true">500</div>
            <i class="bi bi-exclamation-triangle display-3 text-muted mb-3 d-block" aria-hidden="true"></i>

            <h1 class="h2 fw-bold mb-2">Une erreur est survenue</h1>
            <p class="text-muted mb-4">
                Un problème technique inattendu s'est produit. Notre équipe en a été notifiée.
                Veuillez réessayer dans quelques instants.
            </p>

            <a href="/" class="btn btn-vg btn-lg" aria-label="Retourner à l'accueil">
                <i class="bi bi-house me-2"></i>Retour à l'accueil
            </a>

            <div class="mt-4">
                <a href="/contact" class="text-muted small">Contacter le support</a>
            </div>

        </div>
    </div>
</div>
