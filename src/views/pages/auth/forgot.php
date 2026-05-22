<?php
// src/views/pages/auth/forgot.php
$pageTitle = 'Mot de passe oublié - Vite & Gourmand';
?>
<div class="container py-5" style="max-width:480px">
    <div class="card border-0 shadow p-4">
        <div class="text-center mb-4">
            <i class="bi bi-key display-4 text-vg"></i>
            <h1 class="h3 fw-bold mt-2">Mot de passe oublié</h1>
            <p class="text-muted small">Renseignez votre email pour recevoir un lien de réinitialisation.</p>
        </div>

        <form method="POST" action="/mot-de-passe-oublie" novalidate>
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                    aria-required="true"
                    aria-describedby="email-aide"
                    placeholder="votre@email.fr"
                >
                <div id="email-aide" class="form-text">
                    Un lien valable 1 heure vous sera envoyé.
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-vg btn-lg" aria-label="Envoyer le lien de réinitialisation">
                    <i class="bi bi-send me-2"></i>Envoyer le lien
                </button>
            </div>
        </form>

        <hr>
        <p class="text-center small mb-0">
            <a href="/connexion" class="text-muted text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Retour à la connexion
            </a>
        </p>
    </div>
</div>
