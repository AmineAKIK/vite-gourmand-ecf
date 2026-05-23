<?php
$pageTitle = "Personnaliser l'accueil — Vite & Gourmand";
$cspNonce  = $GLOBALS['csp_nonce'] ?? '';

$cfg = function(string $cle, string $default = '') use ($config): string {
    return sanitize($config[$cle] ?? $default);
};
?>

<?php partial('partials/page_title_bar', ['icon' => 'bi-brush', 'title' => "Personnaliser l'accueil"]); ?>

<form method="POST" action="/admin/accueil/modifier" enctype="multipart/form-data" novalidate>
    <?= csrfField() ?>

    <div class="row g-4">

        <!-- COL GAUCHE : textes + preview -->
        <div class="col-12 col-xl-6">

            <!-- Preview hero live -->
            <div class="card shadow-sm mb-4 overflow-hidden">
                <div class="card-header fw-semibold">
                    <i class="bi bi-eye me-2 text-vg"></i>Aperçu en direct
                </div>
                <div class="hero-preview-wrap position-relative text-center" style="min-height:220px;background:#3a0a12;">
                    <img id="preview-hero-bg"
                         src="<?= sanitize(imageUrl($images['hero'] ?? null, 'images/hero-traiteur-bordeaux.webp')) ?>"
                         alt=""
                         aria-hidden="true"
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.45;">
                    <div class="position-relative py-5 px-3" style="z-index:1;">
                        <h2 class="fw-bold text-white mb-2" style="font-family:'Playfair Display',serif;font-size:clamp(1.4rem,4vw,2rem);">
                            Vite &amp; Gourmand
                        </h2>
                        <p id="prev-sous-titre"
                           style="color:var(--vg-or);font-weight:600;font-size:1rem;margin-bottom:.75rem;">
                            <?= $cfg('hero_sous_titre', 'Traiteur bordelais depuis 25 ans') ?>
                        </p>
                        <p id="prev-paragraphe"
                           style="color:rgba(255,255,255,.75);font-size:.9rem;max-width:480px;margin:0 auto;">
                            <?= $cfg('hero_paragraphe', '') ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Textes -->
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-type me-2 text-vg"></i>Textes du hero
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label fw-medium" for="hero_sous_titre">
                            Sous-titre <span class="text-danger">*</span>
                            <small class="text-muted fw-normal ms-1">— affiché en doré</small>
                        </label>
                        <input
                            type="text"
                            id="hero_sous_titre"
                            name="hero_sous_titre"
                            class="form-control"
                            maxlength="60"
                            value="<?= $cfg('hero_sous_titre', 'Traiteur bordelais depuis 25 ans') ?>"
                            required
                        >
                        <div class="d-flex justify-content-between">
                            <div class="form-text">Accroche courte, mise en valeur en couleur dorée.</div>
                            <small class="text-muted mt-1"><span id="count-sous-titre">0</span>/60</small>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-medium" for="hero_paragraphe">
                            Paragraphe d'introduction
                            <small class="text-muted fw-normal ms-1">— affiché en blanc</small>
                        </label>
                        <textarea
                            id="hero_paragraphe"
                            name="hero_paragraphe"
                            class="form-control"
                            rows="3"
                            maxlength="200"
                        ><?= $cfg('hero_paragraphe', '') ?></textarea>
                        <div class="d-flex justify-content-between">
                            <div class="form-text">Description de l'entreprise visible sous le sous-titre.</div>
                            <small class="text-muted mt-1"><span id="count-paragraphe">0</span>/200</small>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- COL DROITE : images -->
        <div class="col-12 col-xl-6">

            <!-- Image hero -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-card-image me-2 text-vg"></i>Image de fond (bannière)
                </div>
                <div class="card-body">
                    <img
                        src="<?= sanitize(imageUrl($images['hero'] ?? null, 'images/hero-traiteur-bordeaux.webp')) ?>"
                        alt="Image hero actuelle"
                        class="img-fluid rounded mb-3"
                        style="max-height:200px;width:100%;object-fit:cover;"
                        id="preview-hero"
                    >
                    <label for="hero" class="form-label fw-medium">Remplacer l'image</label>
                    <input type="file" class="form-control image-picker" id="hero" name="hero"
                           accept="<?= sanitize(MenuAdminService::acceptedImageMimeTypes()) ?>"
                           data-preview="preview-hero"
                           data-preview-bg="preview-hero-bg">
                    <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?> — Recommandé : 1920×600 px</div>
                </div>
            </div>

            <!-- Image préparation -->
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-camera me-2 text-vg"></i>Image section "Notre équipe"
                </div>
                <div class="card-body">
                    <img
                        src="<?= sanitize(imageUrl($images['preparation'] ?? null, 'images/preparation-traiteur.webp')) ?>"
                        alt="Image équipe actuelle"
                        class="img-fluid rounded mb-3"
                        style="max-height:200px;width:100%;object-fit:cover;"
                        id="preview-preparation"
                    >
                    <label for="preparation" class="form-label fw-medium">Remplacer l'image</label>
                    <input type="file" class="form-control image-picker" id="preparation" name="preparation"
                           accept="<?= sanitize(MenuAdminService::acceptedImageMimeTypes()) ?>"
                           data-preview="preview-preparation">
                    <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?> — Recommandé : 1000×700 px</div>
                </div>
            </div>

        </div>

    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-vg">
            <i class="bi bi-save me-2"></i>Enregistrer tout
        </button>
        <a href="/admin" class="btn btn-vg-outline">Annuler</a>
    </div>

</form>

<script nonce="<?= $cspNonce ?>">
(function () {
    // Compteurs de caractères + preview texte live
    function bindCounter(inputId, countId, previewId) {
        var el   = document.getElementById(inputId);
        var cnt  = document.getElementById(countId);
        var prev = document.getElementById(previewId);
        if (!el || !cnt) return;
        function update() {
            cnt.textContent = el.value.length;
            if (prev) prev.textContent = el.value;
        }
        update();
        el.addEventListener('input', update);
    }
    bindCounter('hero_sous_titre', 'count-sous-titre', 'prev-sous-titre');
    bindCounter('hero_paragraphe', 'count-paragraphe', 'prev-paragraphe');

    // Prévisualisation image (miniature + fond hero)
    document.querySelectorAll('input[data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var mini = document.getElementById(input.dataset.preview);
                var bg   = document.getElementById(input.dataset.previewBg || '');
                if (mini) mini.src = e.target.result;
                if (bg)   bg.src  = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        });
    });
}());
</script>
