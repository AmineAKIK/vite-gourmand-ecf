<?php
// src/views/pages/auth/reset.php
$pageTitle = 'Réinitialisation du mot de passe - Vite & Gourmand';
?>
<div class="container py-5" style="max-width:500px">
    <div class="card shadow-sm p-4" style="border:1px solid rgba(0,0,0,.08);">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock display-4 text-vg"></i>
            <h1 class="h3 fw-bold mt-2">Nouveau mot de passe</h1>
            <p class="text-muted small">Choisissez un mot de passe sécurisé.</p>
        </div>

        <form method="POST" action="/reinitialiser" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="token" value="<?= sanitize($token ?? '') ?>">

            <div class="mb-3">
                <label for="password" class="form-label">Nouveau mot de passe</label>
                <div class="input-group">
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                        aria-describedby="regles-mdp"
                    >
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        data-password-toggle="password"
                        aria-label="Afficher ou masquer le mot de passe"
                    >
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                <div class="input-group">
                    <input
                        type="password"
                        class="form-control"
                        id="password_confirm"
                        name="password_conf"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                    >
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        data-password-toggle="password_confirm"
                        aria-label="Afficher ou masquer la confirmation du mot de passe"
                    >
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Règles de sécurité -->
            <div id="regles-mdp" class="alert alert-info small mb-3" role="note" aria-label="Règles de sécurité du mot de passe">
                <strong><i class="bi bi-info-circle me-1"></i>Votre mot de passe doit contenir :</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach (passwordPolicyRules() as $rule): ?>
                        <li><?= sanitize($rule) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-vg btn-lg" aria-label="Enregistrer le nouveau mot de passe">
                    <i class="bi bi-check-lg me-2"></i>Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
