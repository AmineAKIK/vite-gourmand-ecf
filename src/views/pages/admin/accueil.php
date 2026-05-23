<?php
$pageTitle = "Personnaliser l'accueil — Vite & Gourmand";
$cspNonce  = $GLOBALS['csp_nonce'] ?? '';

$cfg = function(string $cle, string $default = '') use ($config): string {
    return sanitize($config[$cle] ?? $default);
};

$defaultParagraphe = 'Depuis 25 ans, Vite & Gourmand accompagne les particuliers et les professionnels avec une cuisine traiteur généreuse, raffinée et préparée à Bordeaux.';
?>

<?php partial('partials/page_title_bar', ['icon' => 'bi-brush', 'title' => "Personnaliser l'accueil"]); ?>

<form method="POST" action="/admin/accueil/modifier" enctype="multipart/form-data" novalidate>
    <?= csrfField() ?>

    <!-- TEXTES HERO — pleine largeur -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-type me-2 text-vg"></i>Textes du hero
        </div>
        <div class="card-body d-flex flex-column gap-4">

            <div>
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

            <div>
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
                ><?= $cfg('hero_paragraphe', $defaultParagraphe) ?></textarea>
                <div class="d-flex justify-content-between">
                    <div class="form-text">Appuie sur Entrée pour créer un saut de ligne sur le site.</div>
                    <small class="text-muted mt-1"><span id="count-paragraphe">0</span>/200</small>
                </div>
            </div>

        </div>
    </div>

    <!-- IMAGES — côte à côte -->
    <div class="row g-4 mb-4">

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
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
                           data-preview="preview-hero">
                    <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?> — Recommandé : 1920×600 px</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
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

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-vg" id="btn-submit">
            <i class="bi bi-save me-2"></i>Enregistrer tout
        </button>
        <a href="/admin/accueil" class="btn btn-vg-outline" id="btn-annuler">Annuler</a>
    </div>

</form>

<script nonce="<?= $cspNonce ?>">
(function () {
    var form    = document.querySelector('form');
    var isDirty = false;
    var submitted = false;

    function markDirty() { isDirty = true; }

    form.querySelectorAll('input, textarea').forEach(function (el) {
        el.addEventListener('input', markDirty);
        el.addEventListener('change', markDirty);
    });

    form.addEventListener('submit', function () { submitted = true; });

    window.addEventListener('beforeunload', function (e) {
        if (isDirty && !submitted) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    document.getElementById('btn-annuler').addEventListener('click', function (e) {
        if (isDirty && !confirm('Des modifications non enregistrées seront perdues. Continuer ?')) {
            e.preventDefault();
        }
    });

    function bindCounter(inputId, countId) {
        var el  = document.getElementById(inputId);
        var cnt = document.getElementById(countId);
        if (!el || !cnt) return;
        function update() { cnt.textContent = el.value.length; }
        update();
        el.addEventListener('input', update);
    }
    bindCounter('hero_sous_titre', 'count-sous-titre');
    bindCounter('hero_paragraphe', 'count-paragraphe');

    document.querySelectorAll('input[data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var mini = document.getElementById(input.dataset.preview);
                if (mini) mini.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        });
    });
}());
</script>
