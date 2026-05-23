<?php
// src/views/pages/employe/commandes.php
$pageTitle = 'Gestion des commandes - Vite & Gourmand';
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-list-check', 'title' => 'Gestion des commandes']); ?>

    <!-- Formulaire de filtres -->
    <div class="filtres-panel card shadow-sm p-3 mb-4" style="border:1px solid rgba(0,0,0,.08);">
        <form method="GET" action="/employe/commandes" class="row g-2 align-items-end" role="search" aria-label="Filtrer les commandes">
            <div class="col-12 col-lg-3">
                <label for="filtre-statut" class="form-label form-label-sm">Statut</label>
                <select class="form-select form-select-sm" id="filtre-statut" name="statut" aria-label="Filtrer par statut">
                    <option value="">— Tous les statuts —</option>
                    <?php foreach ($statuts as $s): ?>
                        <option value="<?= sanitize($s) ?>" <?= ($filters['statut'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= sanitize(commandeStatusLabel($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-5">
                <label for="filtre-client" class="form-label form-label-sm">Rechercher un client</label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="filtre-client"
                    name="client"
                    value="<?= sanitize($filters['client'] ?? '') ?>"
                    placeholder="Nom ou prénom…"
                    aria-label="Rechercher par nom de client"
                >
            </div>
            <div class="col-12 col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-vg btn-sm flex-grow-1" aria-label="Appliquer les filtres">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
                <a href="/employe/commandes" class="btn btn-outline-secondary btn-sm btn-reset-filters flex-grow-1">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- Tableau commandes -->
    <?php if (empty($commandes)): ?>
        <div class="alert alert-info">Aucune commande ne correspond aux critères.</div>
    <?php else: ?>
        <div class="accordion commandes-accordion" id="accordionCommandes">
            <?php foreach ($commandes as $idx => $cmd): ?>
            <div class="accordion-item mb-2" style="border:1px solid rgba(0,0,0,.08);box-shadow:0 1px 4px rgba(0,0,0,.06);border-radius:.5rem;">
                <h2 class="accordion-header" id="heading<?= $cmd['commande_id'] ?>">
                    <button
                        class="accordion-button collapsed py-3"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse<?= $cmd['commande_id'] ?>"
                        aria-expanded="false"
                        aria-controls="collapse<?= $cmd['commande_id'] ?>"
                        style="border-radius:.5rem;"
                    >
                        <div class="commande-row-summary">
                            <code class="commande-numero"><?= sanitize($cmd['numero_commande'] ?? '') ?></code>
                            <strong class="commande-client"><?= sanitize(personFullName($cmd)) ?></strong>
                            <span class="commande-menu"><?= sanitize($cmd['menu_titre'] ?? '') ?></span>
                            <span class="commande-date"><?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></span>
                            <span class="commande-status"><?= commandeStatusBadge($cmd['statut'] ?? null) ?></span>
                        </div>
                    </button>
                </h2>

                <div id="collapse<?= $cmd['commande_id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $cmd['commande_id'] ?>" data-bs-parent="#accordionCommandes">
                    <div class="accordion-body bg-light">
                        <div class="row g-3">

                            <!-- Informations de la commande -->
                            <div class="col-12 col-lg-5">
                                <h3 class="h6 fw-bold">Détails</h3>
                                <dl class="mb-0 small">
                                    <div class="d-flex gap-2 mb-1"><dt class="text-muted" style="min-width:80px;">Client</dt><dd class="mb-0 fw-medium"><?= sanitize(personFullName($cmd)) ?></dd></div>
                                    <div class="d-flex gap-2 mb-1"><dt class="text-muted" style="min-width:80px;">Email</dt><dd class="mb-0" style="word-break:break-all;"><?= sanitize($cmd['email'] ?? '') ?></dd></div>
                                    <div class="d-flex gap-2 mb-1"><dt class="text-muted" style="min-width:80px;">Téléphone</dt><dd class="mb-0"><?= sanitize($cmd['telephone'] ?? '—') ?></dd></div>
                                    <div class="d-flex gap-2 mb-1"><dt class="text-muted" style="min-width:80px;">Menu</dt><dd class="mb-0"><?= sanitize($cmd['menu_titre'] ?? '') ?></dd></div>
                                    <div class="d-flex gap-2 mb-1"><dt class="text-muted" style="min-width:80px;">Personnes</dt><dd class="mb-0"><?= (int)($cmd['nombre_personne'] ?? 0) ?></dd></div>
                                    <div class="d-flex gap-2 mb-1"><dt class="text-muted" style="min-width:80px;">Adresse</dt><dd class="mb-0"><?= sanitize(($cmd['adresse_livraison'] ?? '') . ', ' . ($cmd['ville_livraison'] ?? '')) ?></dd></div>
                                    <div class="d-flex gap-2 mb-0"><dt class="text-muted" style="min-width:80px;">Total</dt><dd class="mb-0 fw-bold text-vg"><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></dd></div>
                                </dl>
                            </div>

                            <!-- Mise à jour du statut -->
                            <div class="col-12 col-lg-4">
                                <h3 class="h6 fw-bold">Mettre à jour le statut</h3>
                                <form method="POST" action="/employe/commande/statut">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                                    <input type="hidden" name="action" value="changer_statut">

                                    <div class="mb-2">
                                        <label for="statut-<?= $cmd['commande_id'] ?>" class="form-label form-label-sm">Nouveau statut</label>
                                        <select
                                            class="form-select form-select-sm"
                                            id="statut-<?= $cmd['commande_id'] ?>"
                                            name="statut"
                                            aria-label="Sélectionner le nouveau statut"
                                        >
                                            <?php foreach ($statuts as $s): ?>
                                                <option value="<?= sanitize($s) ?>" <?= ($cmd['statut'] ?? '') === $s ? 'selected' : '' ?>>
                                                    <?= sanitize(commandeStatusLabel($s)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-2">
                                        <label for="commentaire-<?= $cmd['commande_id'] ?>" class="form-label form-label-sm">Commentaire (optionnel)</label>
                                        <textarea
                                            class="form-control form-control-sm"
                                            id="commentaire-<?= $cmd['commande_id'] ?>"
                                            name="commentaire"
                                            rows="2"
                                            maxlength="500"
                                            aria-label="Commentaire sur le changement de statut"
                                        ></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-vg btn-sm w-100" aria-label="Mettre à jour le statut de la commande">
                                        <i class="bi bi-check-lg me-1"></i>Mettre à jour
                                    </button>
                                </form>
                            </div>

                            <!-- Annulation si la commande est encore modifiable -->
                            <?php if (commandeCanClientModify($cmd)): ?>
                            <div class="col-12 col-lg-3">
                                <h3 class="h6 fw-bold text-danger">Annuler la commande</h3>
                                <form method="POST" action="/employe/commande/statut" class="form-confirm">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                                    <input type="hidden" name="action" value="annuler">
                                    <input type="hidden" name="statut" value="<?= sanitize(commandeCancelledStatus()) ?>">

                                    <div class="mb-2">
                                        <label for="motif-<?= $cmd['commande_id'] ?>" class="form-label form-label-sm">Motif d'annulation</label>
                                        <textarea
                                            class="form-control form-control-sm"
                                            id="motif-<?= $cmd['commande_id'] ?>"
                                            name="commentaire"
                                            rows="2"
                                            maxlength="500"
                                            required
                                            aria-required="true"
                                            aria-label="Motif de l'annulation"
                                        ></textarea>
                                    </div>

                                    <div class="mb-2">
                                        <label for="contact-<?= $cmd['commande_id'] ?>" class="form-label form-label-sm">Contacter le client par</label>
                                        <select
                                            class="form-select form-select-sm"
                                            id="contact-<?= $cmd['commande_id'] ?>"
                                            name="mode_contact"
                                            aria-label="Mode de contact pour l'annulation"
                                        >
                                            <option value="mail">Email</option>
                                            <option value="gsm">GSM</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-danger btn-sm w-100" aria-label="Confirmer l'annulation de la commande">
                                        <i class="bi bi-x-circle me-1"></i>Annuler la commande
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>

                        </div><!-- /row -->
                    </div><!-- /accordion-body -->
                </div>
            </div><!-- /accordion-item -->
            <?php endforeach; ?>
        </div><!-- /accordion -->
    <?php endif; ?>

</div>
