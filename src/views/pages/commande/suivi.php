<?php
// src/views/pages/commande/suivi.php
$pageTitle = 'Suivi de commande #' . sanitize($commande['numero_commande'] ?? '') . ' - Vite & Gourmand';
?>
<div class="container py-5">

    <div class="mb-4">
        <h1 class="h3 fw-bold mb-0">
            Suivi — <span class="text-vg"><?= sanitize($commande['numero_commande'] ?? '') ?></span>
        </h1>
    </div>

    <div class="row g-4">

        <!-- Récapitulatif commande -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100" style="background:var(--vg-creme);">
                <div class="card-header bg-creme fw-bold">
                    <i class="bi bi-receipt me-2"></i>Récapitulatif
                </div>
                <div class="card-body">
                    <dl class="mb-0 small">
                        <div class="d-flex gap-2 mb-2"><dt class="text-muted fw-normal" style="min-width:110px;flex-shrink:0;">Menus</dt><dd class="mb-0 fw-medium"><?= sanitize($commande['menu_titre'] ?? '') ?></dd></div>
                        <div class="d-flex gap-2 mb-2"><dt class="text-muted fw-normal" style="min-width:110px;flex-shrink:0;">Date prestation</dt><dd class="mb-0"><?= sanitize(formatDateFr($commande['date_prestation'] ?? null)) ?></dd></div>
                        <div class="d-flex gap-2 mb-2"><dt class="text-muted fw-normal" style="min-width:110px;flex-shrink:0;">Adresse</dt><dd class="mb-0"><?= sanitize($commande['adresse_livraison'] ?? '') ?><?php if (!empty($commande['ville_livraison'])): ?> — <?= sanitize($commande['ville_livraison']) ?><?php endif; ?></dd></div>
                        <div class="d-flex align-items-baseline gap-2 mb-2"><dt class="text-muted fw-normal" style="min-width:110px;flex-shrink:0;">Prix total</dt><dd class="mb-0 lh-1"><span class="prix-tag"><?= sanitize(formatPrice($commande['prix_total'] ?? 0)) ?></span></dd></div>
                        <div class="d-flex gap-2 mb-0"><dt class="text-muted fw-normal" style="min-width:110px;flex-shrink:0;">Statut actuel</dt><dd class="mb-0"><?= commandeStatusBadge($commande['statut'] ?? null) ?></dd></div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Timeline historique -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm" style="background:var(--vg-creme);">
                <div class="card-header bg-creme fw-bold">
                    <i class="bi bi-clock-history me-2"></i>Historique des statuts
                </div>
                <div class="card-body">
                    <?php if (empty($historique)): ?>
                        <p class="text-muted mb-0">Aucun historique disponible.</p>
                    <?php else: ?>
                        <ol class="list-unstyled" aria-label="Timeline de la commande">
                            <?php foreach ($historique as $i => $h): ?>
                            <li class="d-flex gap-3 mb-4">
                                <!-- Icône et ligne verticale -->
                                <div class="d-flex flex-column align-items-center">
                                    <div
                                        class="rounded-circle bg-vg d-flex align-items-center justify-content-center text-white"
                                        style="width:36px;height:36px;min-width:36px"
                                        aria-hidden="true"
                                    >
                                        <?php if ($i === 0): ?>
                                            <i class="bi bi-flag-fill"></i>
                                        <?php else: ?>
                                            <i class="bi bi-check-lg"></i>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($i < count($historique) - 1): ?>
                                        <div class="flex-grow-1 border-start border-2 border-secondary-subtle" style="width:2px;min-height:30px"></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Contenu -->
                                <div class="pb-2">
                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                        <?= commandeStatusBadge($h['nouveau_statut'] ?? null) ?>
                                        <?php if (!empty($h['ancien_statut'])): ?>
                                            <small class="text-muted">
                                                (depuis : <?= sanitize(commandeStatusLabel($h['ancien_statut'])) ?>)
                                            </small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="small text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?= sanitize(formatDateTimeFr($h['created_at'] ?? null)) ?>
                                    </div>

                                    <?php if (!empty($h['commentaire'])): ?>
                                        <p class="mt-1 mb-0 small fst-italic">
                                            «&nbsp;<?= sanitize($h['commentaire']) ?>&nbsp;»
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div>
