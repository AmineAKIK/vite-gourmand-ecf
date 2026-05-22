<?php $workspaceItems = workspaceNavItems(); ?>
<?php if (!empty($workspaceItems)): ?>
    <nav class="workspace-nav mb-4" aria-label="Navigation de l'espace connecté">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($workspaceItems as $item): ?>
                <?php $isActive = routeIsActive($item['match'] ?? $item['href']); ?>
                <a
                    href="<?= sanitize($item['href']) ?>"
                    class="btn <?= $isActive ? 'btn-vg' : 'btn-outline-secondary' ?> btn-sm"
                    <?= $isActive ? 'aria-current="page"' : '' ?>
                >
                    <i class="bi <?= sanitize($item['icon']) ?> me-1"></i><?= sanitize($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
<?php endif; ?>
