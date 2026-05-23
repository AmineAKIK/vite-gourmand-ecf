<?php
// src/views/pages/auth/login.php
$pageTitle = 'Connexion - Vite & Gourmand';
?>
<div class="container py-5" style="max-width:450px">
    <div class="card shadow-sm p-4" style="border:1px solid rgba(0,0,0,.08);">
        <h1 class="h3 text-center mb-4 fw-bold">Connexion</h1>
        <form method="POST" action="/connexion" novalidate>
            <?= csrfField() ?>
            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="email" aria-required="true">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-label="Afficher/masquer le mot de passe">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-vg btn-lg">Se connecter</button>
            </div>
            <div class="text-center">
                <a href="/mot-de-passe-oublie" class="text-muted small">Mot de passe oublié ?</a>
            </div>
        </form>
        <hr>
        <p class="text-center small mb-0">Pas encore de compte ? <a href="/inscription" class="fw-bold">S'inscrire</a></p>
    </div>
</div>
