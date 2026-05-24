<?php $pageTitle = 'Tableau de bord — Vite & Gourmand'; ?>

<div class="container py-4 workspace-dashboard">

    <h1 class="fw-bold mb-4">
        <i class="bi bi-speedometer2 me-2 text-vg"></i>Tableau de bord
    </h1>

    <!-- ALERTES -->
    <?php $nbAttente = count($commandesEnAttente ?? []); $nbAvis = count($avisEnAttente ?? []); ?>
    <?php if ($nbAttente > 0 || $nbAvis > 0): ?>
    <div class="row g-3 mb-4">
        <?php if ($nbAttente > 0): ?>
        <div class="col-12 col-lg-6">
            <div class="alert alert-warning dashboard-alert d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0 shadow-sm">
                <div>
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <strong><?= $nbAttente ?> commande<?= $nbAttente > 1 ? 's' : '' ?></strong> en attente de confirmation
                </div>
                <a href="/employe/commandes?statut=en_attente" class="btn btn-sm btn-vg">
                    Traiter
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($nbAvis > 0): ?>
        <div class="col-12 col-lg-6">
            <div class="alert alert-info dashboard-alert d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0 shadow-sm">
                <div>
                    <i class="bi bi-star me-2"></i>
                    <strong><?= $nbAvis ?> avis</strong> en attente de validation
                </div>
                <a href="/employe/avis" class="btn btn-sm btn-vg">
                    Valider
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-success d-flex align-items-center mb-4 shadow-sm">
        <i class="bi bi-check-circle me-2"></i>Tout est à jour — aucune action requise.
    </div>
    <?php endif; ?>

    <!-- MÉTRIQUES -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fw-bold text-vg lh-1 mb-1 fs-3"><?= count($commandesAujourdhui ?? []) ?></div>
                <div class="text-muted small">Aujourd'hui</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fw-bold text-vg lh-1 mb-1 fs-3"><?= count($commandesSemaine ?? []) ?></div>
                <div class="text-muted small">Cette semaine</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fw-bold text-vg lh-1 mb-1 fs-3"><?= formatPrice($caSemaine ?? 0) ?></div>
                <div class="text-muted small">CA cette semaine</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fw-bold text-vg lh-1 mb-1 fs-3"><?= count($menusActifs ?? []) ?></div>
                <div class="text-muted small">Menus actifs</div>
            </div>
        </div>
    </div>

    <!-- FIL D'ACTIVITÉ -->
    <div class="card dashboard-activity-card shadow-sm" style="border:1px solid rgba(0,0,0,.08);">
        <div class="card-header fw-semibold">
            <i class="bi bi-clock-history me-2 text-vg"></i>Activité récente
        </div>
        <div class="card-body p-0">
            <?php if (empty($activiteRecente)): ?>
                <p class="text-muted p-3 mb-0">Aucune activité.</p>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($activiteRecente as $cmd): ?>
                <li class="list-group-item dashboard-activity-item py-3 px-3">
                    <div class="dashboard-activity-row">
                        <div class="dashboard-activity-main">
                            <div class="fw-medium text-truncate"><?= sanitize($cmd['numero_commande'] ?? '') ?></div>
                            <div class="text-muted small text-truncate">
                                <?= sanitize(personFullName($cmd)) ?> · <?= sanitize($cmd['menu_titre'] ?? '') ?>
                                · <?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?>
                            </div>
                        </div>
                        <div class="dashboard-activity-meta">
                            <?= commandeStatusBadge($cmd['statut'] ?? null) ?>
                            <span class="fw-semibold text-vg text-nowrap"><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></span>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

</div>
