<?php $pageTitle = buildPageTitle('Inscription'); ?>
<div class="container py-5" style="max-width:600px">
    <div class="card shadow-sm p-4" style="border:1px solid rgba(0,0,0,.08);">
        <h1 class="h3 text-center mb-4 fw-bold">Créer un compte</h1>
        <form method="POST" action="/inscription" novalidate>
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label for="prenom" class="form-label">Prénom <span class="text-danger" aria-label="obligatoire">*</span></label>
                    <input type="text" class="form-control" id="prenom" name="prenom" required autocomplete="given-name">
                </div>
                <div class="col-12 col-lg-6">
                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nom" name="nom" required autocomplete="family-name">
                </div>
                <div class="col-12">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                </div>
                <div class="col-12 col-lg-6">
                    <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="telephone" name="telephone" required autocomplete="tel">
                </div>
                <div class="col-12 col-lg-6">
                    <label for="code_postal" class="form-label">Code postal</label>
                    <input type="text" class="form-control" id="code_postal" name="code_postal" required autocomplete="postal-code">
                </div>
                <div class="col-12">
                    <label for="adresse" class="form-label">Adresse</label>
                    <input type="text" class="form-control" id="adresse" name="adresse" required autocomplete="street-address">
                </div>
                <div class="col-12">
                    <label for="ville" class="form-label">Ville</label>
                    <input type="text" class="form-control" id="ville" name="ville" required autocomplete="address-level2">
                </div>
                <div class="col-12">
                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" data-password-toggle="password" aria-label="Afficher/masquer le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text"><?= sanitize(passwordPolicyMessage()) ?></div>
                </div>
                <div class="col-12">
                    <label for="password_confirm" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" data-password-toggle="password_confirm" aria-label="Afficher/masquer la confirmation">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" class="btn btn-vg btn-lg">Créer mon compte</button>
                </div>
            </div>
        </form>
        <hr>
        <p class="text-center small mb-0">Déjà un compte ? <a href="/connexion" class="fw-bold">Se connecter</a></p>
    </div>
</div>
