<?php $pageTitle = buildPageTitle('Tableau de bord'); ?>

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
            <div class="alert alert-warning dashboard-alert dashboard-action-card mb-0 shadow-sm">
                <span class="dashboard-action-icon">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                </span>
                <div class="dashboard-action-copy">
                    <strong><span><?= $nbAttente ?></span> commande<?= $nbAttente > 1 ? 's' : '' ?></strong>
                    <small>En attente de confirmation</small>
                </div>
                <a href="/employe/commandes?statut=en_attente" class="btn btn-sm btn-vg dashboard-action-btn">
                    Traiter
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($nbAvis > 0): ?>
        <div class="col-12 col-lg-6">
            <div class="alert alert-info dashboard-alert dashboard-action-card mb-0 shadow-sm">
                <span class="dashboard-action-icon">
                    <i class="bi bi-star" aria-hidden="true"></i>
                </span>
                <div class="dashboard-action-copy">
                    <strong><span><?= $nbAvis ?></span> avis</strong>
                    <small>En attente de validation</small>
                </div>
                <a href="/employe/avis" class="btn btn-sm btn-vg dashboard-action-btn">
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

<?php if (!empty($alertesStock)): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong><?= count($alertesStock) ?> ingrédient(s) sous le seuil de stock :</strong>
            <?= implode(', ', array_map(fn($a) => sanitize($a['libelle']) . ' (' . sanitize(formatPriceInput($a['stock_courant'])) . ' ' . sanitize($a['unite']) . ')', $alertesStock)) ?>
            — <a href="/employe/recettes?tab=stocks" class="alert-link fw-semibold">Gérer les stocks</a>
        </div>
    </div>
<?php endif; ?>

    <!-- MÉTRIQUES -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <div class="card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fw-bold text-vg lh-1 mb-1 fs-3"><?= count($commandesAujourdhui ?? []) ?></div>
                <div class="text-muted small">Aujourd'hui</div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fw-bold text-vg lh-1 mb-1 fs-3"><?= count($commandesSemaine ?? []) ?></div>
                <div class="text-muted small">Cette semaine</div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fw-bold text-vg lh-1 mb-1 fs-3"><?= $nbAttente ?></div>
                <div class="text-muted small">En attente de traitement</div>
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
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

</div>
