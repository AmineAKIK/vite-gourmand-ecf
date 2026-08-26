<?php $pageTitle = is_string($seoTitle ?? null) && trim($seoTitle) !== '' ? $seoTitle : buildPageTitle('Contact'); ?>
<div class="container py-5 contact-page">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="text-center mb-5">
                <i class="bi bi-envelope display-4 text-brand"></i>
                <h1 class="fw-bold mt-2"><?= sanitize((is_string($contactTitle ?? null) && trim($contactTitle) !== '') ? $contactTitle : 'Contact') ?></h1>
                <?php if (is_string($contactIntro ?? null) && trim($contactIntro) !== ''): ?><p class="text-muted"><?= sanitize($contactIntro) ?></p><?php endif; ?>
            </div>
            <div class="card p-4">
                <form method="POST" action="/contact" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="titre" class="form-label">Objet du message <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="titre" name="titre" required aria-required="true" maxlength="150" value="<?= sanitize($sujet ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Votre email <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required aria-required="true" autocomplete="email">
                    </div>
                    <div class="mb-4">
                        <label for="description" class="form-label">Message <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="6" required aria-required="true" maxlength="2000"></textarea>
                        <div class="form-text text-end"><span id="compteur">0</span>/2000 caractères</div>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-brand btn-lg" aria-label="Envoyer votre message"><i class="bi bi-send me-2"></i>Envoyer le message</button></div>
                </form>
            </div>
        </div>

        <div class="col-lg-4 mt-4 mt-lg-0 align-self-center">
            <div class="card p-4 h-100">
                <h2 class="h5 fw-bold mb-3"><i class="bi bi-geo-alt me-2 text-brand"></i>Coordonnées</h2>
                <address class="small mb-0">
                    <strong><?= sanitize(siteName()) ?></strong><br>
                    <?php if (siteAddress()): ?><?= sanitize(siteAddress()) ?><br><?php endif; ?>
                    <?php if (sitePostalCode() || siteCity()): ?><?= sanitize(sitePostalCode() . ' ' . siteCity()) ?><br><?php endif; ?>
                    <?php if (sitePhone()): ?><i class="bi bi-telephone me-1"></i><?= sanitize(sitePhone()) ?><br><?php endif; ?>
                    <?php if (siteEmail()): ?><i class="bi bi-envelope me-1"></i><?= sanitize(siteEmail()) ?><?php endif; ?>
                </address>
            </div>
        </div>
    </div>
</div>
<script nonce="<?= cspNonce() ?>">
const textarea = document.getElementById('description');
const compteur = document.getElementById('compteur');
if (textarea && compteur) textarea.addEventListener('input', function () { compteur.textContent = this.value.length; });
</script>
