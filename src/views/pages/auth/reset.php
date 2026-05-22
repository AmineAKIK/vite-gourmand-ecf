<?php
// src/views/pages/auth/reset.php
$pageTitle = 'Réinitialisation du mot de passe - Vite & Gourmand';
?>
<div class="container py-5" style="max-width:500px">
    <div class="card border-0 shadow p-4">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock display-4 text-vg"></i>
            <h1 class="h3 fw-bold mt-2">Nouveau mot de passe</h1>
            <p class="text-muted small">Choisissez un mot de passe sécurisé.</p>
        </div>

        <form method="POST" action="/reinitialiser" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
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
                        id="togglePwd"
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
                        id="togglePwdConfirm"
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
                    <li>Au moins <strong>10 caractères</strong></li>
                    <li>Au moins une <strong>majuscule</strong> (A–Z)</li>
                    <li>Au moins une <strong>minuscule</strong> (a–z)</li>
                    <li>Au moins un <strong>chiffre</strong> (0–9)</li>
                    <li>Au moins un <strong>caractère spécial</strong> (!@#$%…)</li>
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

<script>
/* Bascule visibilité mot de passe */
function toggleVisibility(btnId, inputId) {
    const btn   = document.getElementById(btnId);
    const input = document.getElementById(inputId);
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
}
toggleVisibility('togglePwd', 'password');
toggleVisibility('togglePwdConfirm', 'password_confirm');
</script>
