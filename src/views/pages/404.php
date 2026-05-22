<?php
// src/views/pages/404.php
$pageTitle = 'Page introuvable - Vite & Gourmand';
?>
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">

            <div class="display-1 fw-bold text-vg mb-2" aria-hidden="true">404</div>
            <i class="bi bi-compass display-3 text-muted mb-3 d-block" aria-hidden="true"></i>

            <h1 class="h2 fw-bold mb-2">Page introuvable</h1>
            <p class="text-muted mb-4">
                La page que vous recherchez n'existe pas ou a été déplacée.
                Pas de panique — revenez sur vos pas !
            </p>

            <a href="/" class="btn btn-vg btn-lg" aria-label="Retourner à l'accueil">
                <i class="bi bi-house me-2"></i>Retour à l'accueil
            </a>

            <div class="mt-4">
                <a href="/menus" class="text-muted small me-3">Nos menus</a>
                <a href="/contact" class="text-muted small">Contact</a>
            </div>

        </div>
    </div>
</div>
