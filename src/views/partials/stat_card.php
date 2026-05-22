<div class="<?= sanitize($columnClass ?? 'col-sm-4') ?>">
    <div class="card border-0 shadow-sm text-center p-3 h-100">
        <div class="display-6 fw-bold <?= sanitize($valueClass ?? 'text-vg') ?>"><?= sanitize((string)($value ?? '')) ?></div>
        <div class="small text-muted mt-1">
            <i class="bi <?= sanitize($icon ?? 'bi-info-circle') ?> me-1"></i><?= sanitize($label ?? '') ?>
        </div>
    </div>
</div>
