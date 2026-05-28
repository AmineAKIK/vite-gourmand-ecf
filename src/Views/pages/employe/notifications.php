<?php $pageTitle = buildPageTitle('Notifications'); ?>

<div class="container py-4">
    <?php partial('partials/page_title_bar', ['icon' => 'bi-bell', 'title' => 'Notifications']); ?>

    <?php if (empty($notifications)): ?>
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-bell-slash"></i>
            Aucune notification.
        </div>
    <?php else: ?>
    <div class="list-group shadow-sm">
        <?php foreach ($notifications as $notif): ?>
        <div class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3
            <?= $notif['lu'] ? '' : 'notif-unread' ?>">
            <span class="notif-icon flex-shrink-0">
                <?php if ($notif['type'] === 'nouvelle_commande'): ?>
                    <i class="bi bi-bag-plus text-warning fs-5"></i>
                <?php elseif ($notif['type'] === 'statut_commande'): ?>
                    <i class="bi bi-arrow-repeat text-info fs-5"></i>
                <?php else: ?>
                    <i class="bi bi-bell text-vg fs-5"></i>
                <?php endif; ?>
            </span>
            <div class="flex-grow-1">
                <div class="fw-semibold <?= $notif['lu'] ? 'text-muted' : '' ?>">
                    <?= sanitize($notif['titre']) ?>
                </div>
                <?php if ($notif['corps']): ?>
                    <div class="text-muted small"><?= sanitize($notif['corps']) ?></div>
                <?php endif; ?>
                <div class="text-muted" style="font-size:.75rem;">
                    <?= sanitize(formatDateTimeFr($notif['created_at'])) ?>
                </div>
            </div>
            <?php if ($notif['commande_id']): ?>
            <a href="/employe/commandes?q=<?= (int)$notif['commande_id'] ?>"
               class="btn btn-sm btn-vg-outline flex-shrink-0 align-self-center">
                Voir
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style nonce="<?= cspNonce() ?>">
.notif-unread { background: rgba(var(--bs-warning-rgb), .06); border-left: 3px solid var(--vg-or, #D4A843); }
</style>
