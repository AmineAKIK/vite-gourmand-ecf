<?php $pageTitle = buildPageTitle('Tableau de bord'); ?>
<div class="container py-4">
    <h1 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2 text-brand"></i>Tableau de bord</h1>
    <?php $nbAttente = count($commandesEnAttente ?? []); $nbAvis = count($avisEnAttente ?? []); ?>
    <?php if ($nbAttente > 0 || $nbAvis > 0): ?>
        <div class="row g-3 mb-4">
            <?php if ($nbAttente > 0): ?><div class="col-12 col-lg-6"><div class="alert alert-warning mb-0 d-flex align-items-center gap-3"><i class="bi bi-exclamation-circle fs-4"></i><div class="flex-grow-1"><strong><?= $nbAttente ?> commande<?= $nbAttente > 1 ? 's' : '' ?></strong><div class="small">En attente de confirmation</div></div><a href="/employe/commandes?statut=en_attente" class="btn btn-sm btn-brand">Traiter</a></div></div><?php endif; ?>
            <?php if ($nbAvis > 0): ?><div class="col-12 col-lg-6"><div class="alert alert-info mb-0 d-flex align-items-center gap-3"><i class="bi bi-star fs-4"></i><div class="flex-grow-1"><strong><?= $nbAvis ?> avis</strong><div class="small">En attente de validation</div></div><a href="/employe/avis" class="btn btn-sm btn-brand">Valider</a></div></div><?php endif; ?>
        </div>
    <?php else: ?><div class="alert alert-success d-flex align-items-center mb-4"><i class="bi bi-check-circle me-2"></i>Tout est à jour — aucune action requise.</div><?php endif; ?>

    <div class="metric-grid mb-4">
        <div class="metric-card text-center"><div class="metric-value text-brand"><?= count($commandesAujourdhui ?? []) ?></div><div class="text-muted small">Aujourd'hui</div></div>
        <div class="metric-card text-center"><div class="metric-value text-brand"><?= count($commandesSemaine ?? []) ?></div><div class="text-muted small">Cette semaine</div></div>
        <div class="metric-card text-center"><div class="metric-value text-brand"><?= formatPrice($caSemaine ?? 0) ?></div><div class="text-muted small">CA cette semaine</div></div>
        <div class="metric-card text-center"><div class="metric-value text-brand"><?= count($menusActifs ?? []) ?></div><div class="text-muted small">Menus actifs</div></div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2 text-brand"></i>Activité récente</div>
        <div class="card-body p-0">
            <?php if (empty($activiteRecente)): ?><p class="text-muted p-3 mb-0">Aucune activité.</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($activiteRecente as $cmd): ?><li class="list-group-item py-3 px-3"><div class="d-flex justify-content-between align-items-center gap-3"><div class="min-w-0"><div class="fw-medium text-truncate"><?= sanitize($cmd['numero_commande'] ?? '') ?></div><div class="text-muted small text-truncate"><?= sanitize(personFullName($cmd)) ?> · <?= sanitize($cmd['menu_titre'] ?? '') ?> · <?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></div></div><div class="d-flex align-items-center gap-2"><?= commandeStatusBadge($cmd['statut'] ?? null) ?><span class="fw-semibold text-brand text-nowrap"><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></span></div></div></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
    </div>
</div>
