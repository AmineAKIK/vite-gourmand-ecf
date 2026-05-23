<?php
$pageTitle = 'Images du site - Vite & Gourmand';
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-image', 'title' => 'Images du site']); ?>

    <form method="POST" action="/admin/images/modifier" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <div class="row g-4">

            <!-- Hero -->
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm p-4" style="background:var(--vg-creme);">
                    <h2 class="h5 fw-bold mb-3">
                        <i class="bi bi-card-image me-2 text-vg"></i>Image hero (bannière d'accueil)
                    </h2>
                    <div class="mb-3">
                        <img
                            src="<?= sanitize(imageUrl($images['hero'] ?? null)) ?>"
                            alt="Image hero actuelle"
                            class="img-fluid rounded shadow-sm"
                            style="max-height:220px;width:100%;object-fit:cover;"
                            id="preview-hero"
                        >
                    </div>
                    <label for="hero" class="form-label fw-medium">Remplacer l'image</label>
                    <input type="file" class="form-control image-picker" id="hero" name="hero"
                           accept="<?= sanitize(MenuAdminService::acceptedImageMimeTypes()) ?>"
                           data-preview="preview-hero">
                    <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?> — Format recommandé : 1920×600 px</div>
                </div>
            </div>

            <!-- Preparation -->
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm p-4" style="background:var(--vg-creme);">
                    <h2 class="h5 fw-bold mb-3">
                        <i class="bi bi-camera me-2 text-vg"></i>Image section "Notre équipe"
                    </h2>
                    <div class="mb-3">
                        <img
                            src="<?= sanitize(imageUrl($images['preparation'] ?? null)) ?>"
                            alt="Image équipe actuelle"
                            class="img-fluid rounded shadow-sm"
                            style="max-height:220px;width:100%;object-fit:cover;"
                            id="preview-preparation"
                        >
                    </div>
                    <label for="preparation" class="form-label fw-medium">Remplacer l'image</label>
                    <input type="file" class="form-control image-picker" id="preparation" name="preparation"
                           accept="<?= sanitize(MenuAdminService::acceptedImageMimeTypes()) ?>"
                           data-preview="preview-preparation">
                    <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?> — Format recommandé : 1000×700 px</div>
                </div>
            </div>

        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-vg">
                <i class="bi bi-cloud-upload me-2"></i>Enregistrer les images
            </button>
            <a href="/admin" class="btn btn-vg-outline">Annuler</a>
        </div>
    </form>
</div>

<script nonce="<?= cspNonce() ?>">
// Prévisualisation instantanée avant upload
document.querySelectorAll('input[data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
        var previewId = input.dataset.preview;
        var preview = document.getElementById(previewId);
        if (!preview || !input.files[0]) return;
        var reader = new FileReader();
        reader.onload = function (e) { preview.src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    });
});
</script>
