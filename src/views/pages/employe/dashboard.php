<?php $pageTitle = 'Tableau de bord — Vite & Gourmand'; ?>

<div class="container py-4">

    <h1 class="fw-bold mb-4">
        <i class="bi bi-speedometer2 me-2 text-vg"></i>Tableau de bord
    </h1>

    <!-- ALERTES -->
    <?php $nbAttente = count($commandesEnAttente ?? []); $nbAvis = count($avisEnAttente ?? []); ?>
    <?php if ($nbAttente > 0 || $nbAvis > 0): ?>
    <div class="row g-3 mb-4">
        <?php if ($nbAttente > 0): ?>
        <div class="col-12 col-md-6">
            <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0 shadow-sm">
                <div>
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <strong><?= $nbAttente ?> commande<?= $nbAttente > 1 ? 's' : '' ?></strong> en attente de confirmation
                </div>
                <a href="/employe/commandes?statut=en_attente" class="btn btn-sm btn-vg">
                    <i class="bi bi-arrow-right me-1"></i>Traiter
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($nbAvis > 0): ?>
        <div class="col-12 col-md-6">
            <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0 shadow-sm">
                <div>
                    <i class="bi bi-star me-2"></i>
                    <strong><?= $nbAvis ?> avis</strong> en attente de validation
                </div>
                <a href="/employe/avis" class="btn btn-sm btn-vg">
                    <i class="bi bi-arrow-right me-1"></i>Valider
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
        <div class="col-6 col-md-4">
            <div class="card shadow-sm p-3 text-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fs-1 fw-bold text-vg lh-1"><?= count($commandesAujourdhui ?? []) ?></div>
                <div class="text-muted small mt-1">Commandes aujourd'hui</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card shadow-sm p-3 text-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fs-1 fw-bold text-vg lh-1"><?= count($commandesSemaine ?? []) ?></div>
                <div class="text-muted small mt-1">Commandes cette semaine</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm p-3 text-center" style="border:1px solid rgba(0,0,0,.08);">
                <div class="fs-1 fw-bold text-vg lh-1"><?= $nbAttente ?></div>
                <div class="text-muted small mt-1">En attente de traitement</div>
            </div>
        </div>
    </div>

    <!-- FIL D'ACTIVITÉ -->
    <div class="card shadow-sm" style="border:1px solid rgba(0,0,0,.08);">
        <div class="card-header fw-semibold">
            <i class="bi bi-clock-history me-2 text-vg"></i>Activité récente
        </div>
        <div class="card-body p-0">
            <?php if (empty($activiteRecente)): ?>
                <p class="text-muted p-3 mb-0">Aucune activité.</p>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($activiteRecente as $cmd): ?>
                <li class="list-group-item d-flex align-items-center justify-content-between gap-3 py-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-receipt text-vg"></i>
                        <div>
                            <div class="fw-medium"><?= sanitize($cmd['numero_commande'] ?? '') ?></div>
                            <div class="text-muted small">
                                <?= sanitize(personFullName($cmd)) ?> · <?= sanitize($cmd['menu_titre'] ?? '') ?>
                                · <?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?>
                            </div>
                        </div>
                    </div>
                    <?= commandeStatusBadge($cmd['statut'] ?? null) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

</div>
