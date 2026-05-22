<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <?php if (($showBackLink ?? true) === true): ?>
        <?php require __DIR__ . '/dashboard_back_link.php'; ?>
    <?php endif; ?>
    <h1 class="<?= sanitize($titleClass ?? 'h3 fw-bold mb-0') ?>">
        <i class="bi <?= sanitize($icon ?? 'bi-speedometer2') ?> me-2 text-vg"></i><?= sanitize($title ?? '') ?>
    </h1>
</div>
