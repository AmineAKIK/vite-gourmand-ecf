<?php
$pageTitle = buildPageTitle('Images du site');
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-image', 'title' => 'Images du site']); ?>

    <form method="POST" action="/admin/images/modifier" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <div class="row g-4">

            <!-- Logo -->
            <div class="col-12">
                <div class="card border-0 shadow-sm p-4" style="background:var(--vg-creme);">
                    <h2 class="h5 fw-bold mb-1">
                        <i class="bi bi-image-fill me-2 text-vg"></i>Logo du site
                    </h2>
                    <p class="text-muted small mb-3">Utilisé dans la navbar, le favicon et l'aperçu des réseaux sociaux. Si absent, le nom du site s'affiche à la place.</p>
                    <div class="d-flex align-items-center gap-4 mb-3">
                        <?php if (!empty($images['logo'])): ?>
                            <img src="<?= sanitize(imageUrl($images['logo'])) ?>"
                                 alt="Logo actuel"
                                 id="preview-logo"
                                 style="max-height:80px;max-width:200px;object-fit:contain;background:#fff;padding:8px;border-radius:8px;border:1px solid #ddd;">
                        <?php else: ?>
                            <div id="preview-logo" class="d-flex align-items-center justify-content-center text-muted"
                                 style="height:80px;width:200px;background:#fff;border:1px dashed #ccc;border-radius:8px;font-size:13px;">
                                Aucun logo
                            </div>
                        <?php endif; ?>
                    </div>
                    <label for="logo" class="form-label fw-medium">Uploader un logo</label>
                    <input type="file" class="form-control image-picker" id="logo" name="logo"
                           accept="<?= sanitize(MenuAdminService::acceptedImageMimeTypes()) ?>"
                           data-preview="preview-logo">
                    <div class="form-text"><?= sanitize(MenuAdminService::acceptedImageFormatsLabel()) ?> — Format recommandé : PNG transparent, min. 300×100 px</div>
                </div>
            </div>

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
