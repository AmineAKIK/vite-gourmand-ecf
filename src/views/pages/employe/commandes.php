<?php
// src/views/pages/employe/commandes.php
$pageTitle = 'Gestion des commandes - Vite & Gourmand';
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-list-check', 'title' => 'Gestion des commandes']); ?>

    <!-- Formulaire de filtres -->
    <div class="filtres-panel card shadow-sm p-3 mb-4" style="border:1px solid rgba(0,0,0,.08);">
        <form method="GET" action="/employe/commandes" class="row g-2 align-items-end" role="search" aria-label="Filtrer les commandes">
            <div class="col-md-4">
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
            <div class="col-md-5">
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
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-vg btn-sm flex-grow-1" aria-label="Appliquer les filtres">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
                <a href="/employe/commandes" class="btn btn-outline-secondary btn-sm" aria-label="Réinitialiser les filtres">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tableau commandes -->
    <?php if (empty($commandes)): ?>
        <div class="alert alert-info">Aucune commande ne correspond aux critères.</div>
    <?php else: ?>
        <div class="accordion" id="accordionCommandes">
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
                        <div class="d-flex flex-wrap gap-2 align-items-center w-100 pe-3">
                            <code class="text-muted small" style="font-size:.75rem;"><?= sanitize($cmd['numero_commande'] ?? '') ?></code>
                            <span class="vr mx-1 opacity-25"></span>
                            <strong class="fw-semibold"><?= sanitize(personFullName($cmd)) ?></strong>
                            <span class="text-muted">·</span>
                            <span class="text-muted"><?= sanitize($cmd['menu_titre'] ?? '') ?></span>
                            <div class="ms-auto d-flex align-items-center gap-3">
                                <span class="text-muted small"><?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></span>
                                <?= commandeStatusBadge($cmd['statut'] ?? null) ?>
                            </div>
                        </div>
                    </button>
                </h2>

                <div id="collapse<?= $cmd['commande_id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $cmd['commande_id'] ?>" data-bs-parent="#accordionCommandes">
                    <div class="accordion-body bg-light">
                        <div class="row g-3">

                            <!-- Informations de la commande -->
                            <div class="col-md-5">
                                <h3 class="h6 fw-bold">Détails</h3>
                                <dl class="row small mb-0">
                                    <dt class="col-5">Client</dt>
                                    <dd class="col-7"><?= sanitize(personFullName($cmd)) ?></dd>
                                    <dt class="col-5">Email</dt>
                                    <dd class="col-7"><?= sanitize($cmd['email'] ?? '') ?></dd>
                                    <dt class="col-5">Téléphone</dt>
                                    <dd class="col-7"><?= sanitize($cmd['telephone'] ?? '—') ?></dd>
                                    <dt class="col-5">Menu</dt>
                                    <dd class="col-7"><?= sanitize($cmd['menu_titre'] ?? '') ?></dd>
                                    <dt class="col-5">Personnes</dt>
                                    <dd class="col-7"><?= (int)($cmd['nombre_personne'] ?? 0) ?></dd>
                                    <dt class="col-5">Adresse</dt>
                                    <dd class="col-7"><?= sanitize(($cmd['adresse_livraison'] ?? '') . ' ' . ($cmd['ville_livraison'] ?? '')) ?></dd>
                                    <dt class="col-5">Prix total</dt>
                                    <dd class="col-7 fw-bold"><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></dd>
                                </dl>
                            </div>

                            <!-- Mise à jour du statut -->
                            <div class="col-md-4">
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
                            <div class="col-md-3">
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
