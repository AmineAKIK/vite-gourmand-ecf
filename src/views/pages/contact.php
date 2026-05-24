<?php
// src/views/pages/contact.php
$pageTitle = 'Contact - Vite & Gourmand';
?>
<div class="container py-5 contact-page">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <div class="text-center mb-5">
                <i class="bi bi-envelope-heart display-4 text-vg"></i>
                <h1 class="fw-bold mt-2">Contactez-nous</h1>
                <p class="text-muted">Une question, une demande particulière ? Nous vous répondons sous 48h.</p>
            </div>

            <div class="card contact-panel p-4">
                <form method="POST" action="/contact" novalidate>
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="titre" class="form-label">Objet du message <span class="text-danger" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="titre"
                            name="titre"
                            required
                            aria-required="true"
                            maxlength="150"
                            placeholder="Ex : Demande de devis mariage"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Votre email <span class="text-danger" aria-hidden="true">*</span></label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            required
                            aria-required="true"
                            autocomplete="email"
                            placeholder="votre@email.fr"
                        >
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Message <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea
                            class="form-control"
                            id="description"
                            name="description"
                            rows="6"
                            required
                            aria-required="true"
                            maxlength="2000"
                            placeholder="Décrivez votre demande en détail…"
                        ></textarea>
                        <div class="form-text text-end"><span id="compteur">0</span>/2000 caractères</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-vg btn-lg" aria-label="Envoyer votre message">
                            <i class="bi bi-send me-2"></i>Envoyer le message
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Infos pratiques -->
        <div class="col-lg-4 mt-4 mt-lg-0 align-self-center">
            <div class="card contact-panel p-4 h-100">
                <h2 class="h5 fw-bold mb-3"><i class="bi bi-geo-alt me-2 text-vg"></i>Nous trouver</h2>
                <address class="small">
                    <strong>Vite &amp; Gourmand</strong><br>
                    12 rue des Capucins<br>
                    33000 Bordeaux<br>
                    <i class="bi bi-telephone me-1"></i>05 56 00 12 34<br>
                    <i class="bi bi-envelope me-1"></i>contact@vitegourmand.fr
                </address>
                <hr>
                <h2 class="h6 fw-bold mb-2"><i class="bi bi-clock me-2 text-vg"></i>Délai de réponse</h2>
                <p class="small mb-0">Nous vous répondons dans les <strong>48 heures</strong> ouvrées.</p>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
/* Compteur de caractères textarea */
const textarea  = document.getElementById('description');
const compteur  = document.getElementById('compteur');
if (textarea && compteur) {
    textarea.addEventListener('input', function () {
        compteur.textContent = this.value.length;
    });
}
</script>
