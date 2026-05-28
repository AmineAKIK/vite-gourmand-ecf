<?php
$pageTitle     = buildPageTitle('Suivi commande #' . sanitize($commande['numero_commande'] ?? ''));
$statuts       = ['en_attente', 'accepte', 'en_preparation', 'en_cours_livraison', 'livre', 'en_attente_materiel', 'terminee'];
$statutActuel  = $commande['statut'] ?? 'en_attente';
$isAnnulee     = $statutActuel === 'annulee';
$statutIndex   = array_search($statutActuel, $statuts);
?>
<div class="container py-5 suivi-page">

    <div class="mb-4 d-flex align-items-center gap-3 flex-wrap">
        <a href="/mon-compte" class="btn btn-vg-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Mes commandes
        </a>
        <h1 class="h3 fw-bold mb-0">
            Suivi — <span class="text-vg"><?= sanitize($commande['numero_commande'] ?? '') ?></span>
        </h1>
        <?= commandeStatusBadge($statutActuel) ?>
    </div>

    <!-- PROGRESS STEPS -->
    <?php if (!$isAnnulee): ?>
    <div class="card shadow-sm mb-4 p-3 p-md-4">
        <div class="suivi-steps" role="list" aria-label="Progression de la commande">
            <?php
            $stepLabels = [
                'en_attente'          => ['label' => 'En attente',      'icon' => 'bi-hourglass-split'],
                'accepte'             => ['label' => 'Acceptée',        'icon' => 'bi-check-circle'],
                'en_preparation'      => ['label' => 'Préparation',     'icon' => 'bi-fire'],
                'en_cours_livraison'  => ['label' => 'En livraison',    'icon' => 'bi-truck'],
                'livre'               => ['label' => 'Livré',           'icon' => 'bi-house-check'],
                'en_attente_materiel' => ['label' => 'Retour matériel', 'icon' => 'bi-box-arrow-in-left'],
                'terminee'            => ['label' => 'Terminée',        'icon' => 'bi-star-fill'],
            ];
            foreach ($statuts as $i => $s):
                $isDone    = $statutIndex !== false && $i < $statutIndex;
                $isCurrent = $s === $statutActuel;
                $cls = $isCurrent ? 'suivi-step--current' : ($isDone ? 'suivi-step--done' : 'suivi-step--pending');
            ?>
            <div class="suivi-step <?= $cls ?>" role="listitem" aria-current="<?= $isCurrent ? 'step' : 'false' ?>">
                <div class="suivi-step-icon">
                    <?php if ($isDone): ?>
                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                    <?php else: ?>
                        <i class="bi <?= $stepLabels[$s]['icon'] ?>" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="suivi-step-label"><?= sanitize($stepLabels[$s]['label']) ?></div>
                <?php if ($i < count($statuts) - 1): ?>
                    <div class="suivi-step-connector" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-x-circle-fill fs-5"></i>
        <div>
            <strong>Commande annulée.</strong>
            <?php if (!empty($commande['motif_annulation'])): ?>
                Motif : <?= sanitize($commande['motif_annulation']) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Récapitulatif -->
        <div class="col-lg-5">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-receipt me-2 text-vg"></i>Récapitulatif
                </div>
                <div class="card-body">
                    <dl class="mb-0 small">
                        <div class="d-flex gap-2 mb-2">
                            <dt class="text-muted fw-normal suivi-dt">Menu(s)</dt>
                            <dd class="mb-0 fw-medium"><?= sanitize($commande['menu_titre'] ?? '') ?></dd>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <dt class="text-muted fw-normal suivi-dt">Date prestation</dt>
                            <dd class="mb-0"><?= sanitize(formatDateFr($commande['date_prestation'] ?? null)) ?>
                                <?php if (!empty($commande['heure_livraison'])): ?>
                                    à <?= sanitize($commande['heure_livraison']) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <dt class="text-muted fw-normal suivi-dt">Adresse</dt>
                            <dd class="mb-0">
                                <?= sanitize($commande['adresse_livraison'] ?? '') ?>
                                <?php if (!empty($commande['ville_livraison'])): ?>
                                    — <?= sanitize($commande['code_postal_livraison'] ?? '') ?> <?= sanitize($commande['ville_livraison']) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="d-flex align-items-baseline gap-2 mb-0">
                            <dt class="text-muted fw-normal suivi-dt">Total</dt>
                            <dd class="mb-0"><strong class="text-vg"><?= sanitize(formatPrice($commande['prix_total'] ?? 0)) ?></strong></dd>
                        </div>
                    </dl>

                    <hr class="my-3">

                    <a href="/contact?sujet=<?= urlencode('Commande #' . ($commande['numero_commande'] ?? '')) ?>"
                       class="btn btn-vg-outline btn-sm w-100">
                        <i class="bi bi-chat-dots me-2"></i>Contacter le traiteur
                    </a>
                </div>
            </div>
        </div>

        <!-- Historique timeline -->
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-clock-history me-2 text-vg"></i>Historique
                </div>
                <div class="card-body">
                    <?php if (empty($historique)): ?>
                        <p class="text-muted mb-0">Aucun historique disponible.</p>
                    <?php else: ?>
                    <ol class="list-unstyled mb-0" aria-label="Historique de la commande">
                        <?php foreach ($historique as $i => $h): ?>
                        <li class="d-flex gap-3 <?= $i < count($historique) - 1 ? 'mb-4' : '' ?>">
                            <div class="d-flex flex-column align-items-center">
                                <div class="rounded-circle bg-vg d-flex align-items-center justify-content-center text-white"
                                     style="width:34px;height:34px;min-width:34px;" aria-hidden="true">
                                    <?php if ($i === 0): ?>
                                        <i class="bi bi-flag-fill fs-6"></i>
                                    <?php else: ?>
                                        <i class="bi bi-check-lg fs-6"></i>
                                    <?php endif; ?>
                                </div>
                                <?php if ($i < count($historique) - 1): ?>
                                    <div class="flex-grow-1 border-start border-2 border-secondary-subtle" style="width:2px;min-height:28px;"></div>
                                <?php endif; ?>
                            </div>
                            <div class="pb-1">
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                    <?= commandeStatusBadge($h['nouveau_statut'] ?? null) ?>
                                    <?php if (!empty($h['ancien_statut'])): ?>
                                        <small class="text-muted">(depuis : <?= sanitize(commandeStatusLabel($h['ancien_statut'])) ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-calendar3 me-1"></i><?= sanitize(formatDateTimeFr($h['created_at'] ?? null)) ?>
                                </div>
                                <?php if (!empty($h['commentaire'])): ?>
                                    <p class="mt-1 mb-0 small fst-italic text-muted">«&nbsp;<?= sanitize($h['commentaire']) ?>&nbsp;»</p>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<style nonce="<?= cspNonce() ?>">
.suivi-dt { min-width: 120px; flex-shrink: 0; }

.suivi-steps {
    display: flex;
    align-items: flex-start;
    gap: 0;
    overflow-x: auto;
    padding-bottom: .25rem;
}
.suivi-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 60px;
    position: relative;
    text-align: center;
}
.suivi-step-icon {
    width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; position: relative; z-index: 1;
    transition: background .2s;
}
.suivi-step--done .suivi-step-icon    { background: var(--vg-bordeaux, #8B1A2B); color: #fff; }
.suivi-step--current .suivi-step-icon { background: var(--vg-or, #D4A843); color: #fff; box-shadow: 0 0 0 4px rgba(212,168,67,.25); }
.suivi-step--pending .suivi-step-icon { background: #e9ecef; color: #adb5bd; }
.suivi-step-label { font-size: .7rem; margin-top: .35rem; color: #6c757d; line-height: 1.2; }
.suivi-step--done .suivi-step-label    { color: var(--vg-bordeaux, #8B1A2B); font-weight: 600; }
.suivi-step--current .suivi-step-label { color: #856404; font-weight: 700; }

.suivi-step-connector {
    position: absolute;
    top: 19px; left: 50%;
    width: 100%; height: 2px;
    background: #dee2e6;
    z-index: 0;
}
.suivi-step--done + .suivi-step .suivi-step-connector,
.suivi-step--done .suivi-step-connector { background: var(--vg-bordeaux, #8B1A2B); }
</style>
