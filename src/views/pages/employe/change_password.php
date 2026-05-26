<?php $pageTitle = buildPageTitle('Changer mon mot de passe'); ?>

<div class="container py-5" style="max-width:480px">
    <div class="card shadow-sm p-4" style="border:1px solid rgba(0,0,0,.08);">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock display-4 text-vg"></i>
            <h1 class="h3 fw-bold mt-2">Choisir un mot de passe</h1>
            <p class="text-muted small">Pour des raisons de sécurité, vous devez définir un mot de passe personnel avant d'accéder à votre espace.</p>
        </div>

        <form method="POST" action="/employe/changer-mot-de-passe" novalidate>
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                    >
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-label="Afficher/masquer le mot de passe">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label for="password_confirm" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input
                        type="password"
                        class="form-control"
                        id="password_confirm"
                        name="password_confirm"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                    >
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="password_confirm" aria-label="Afficher/masquer la confirmation">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="alert alert-info small mb-3" role="note">
                <strong><i class="bi bi-info-circle me-1"></i>Votre mot de passe doit contenir :</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach (passwordPolicyRules() as $rule): ?>
                        <li><?= sanitize($rule) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-vg btn-lg">
                    <i class="bi bi-check-lg me-2"></i>Enregistrer et accéder à mon espace
                </button>
            </div>
        </form>
    </div>
</div>
