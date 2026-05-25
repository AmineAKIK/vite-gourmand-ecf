<?php
// src/views/pages/employe/commandes.php
$pageTitle = 'Gestion des commandes - Vite & Gourmand';
$statutsMiseAJour = array_values(array_filter(
    $statuts,
    fn($statut) => $statut !== commandeCancelledStatus()
));
?>
<div class="container py-5 commandes-page">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-list-check', 'title' => 'Gestion des commandes']); ?>

    <!-- Formulaire de filtres -->
    <div class="filtres-panel commandes-filter-panel p-3 mb-4">
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
                    Réinitialiser
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
            <?php
                $statutActuel = $cmd['statut'] ?? null;
                $statutsDisponibles = array_values(array_filter(
                    $statutsMiseAJour,
                    fn($statut) => commandeCanTransition($statutActuel, $statut)
                ));
                $peutAnnuler = $statutActuel !== commandeCancelledStatus()
                    && commandeCanTransition($statutActuel, commandeCancelledStatus());
            ?>
            <div class="accordion-item commande-card mb-3">
                <h2 class="accordion-header" id="heading<?= $cmd['commande_id'] ?>">
                    <button
                        class="accordion-button collapsed commande-card-header py-3"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse<?= $cmd['commande_id'] ?>"
                        aria-expanded="false"
                        aria-controls="collapse<?= $cmd['commande_id'] ?>"
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
                    <div class="accordion-body commande-card-body">
                        <div class="row g-4 align-items-start">

                            <!-- Informations de la commande -->
                            <div class="col-12 col-lg-5">
                                <section class="commande-section h-100">
                                    <h3 class="h6 fw-bold">Détails</h3>
                                    <dl class="commande-detail-list mb-0 small">
                                        <div><dt>Client</dt><dd><?= sanitize(personFullName($cmd)) ?></dd></div>
                                        <div><dt>Email</dt><dd class="text-break"><?= sanitize($cmd['email'] ?? '') ?></dd></div>
                                        <div><dt>Téléphone</dt><dd><?= sanitize($cmd['telephone'] ?? '—') ?></dd></div>
                                        <div><dt>Menus</dt><dd><?= sanitize($cmd['menu_titre'] ?? '') ?></dd></div>
                                        <div><dt>Adresse</dt><dd><?= sanitize(($cmd['adresse_livraison'] ?? '') . ', ' . ($cmd['ville_livraison'] ?? '')) ?></dd></div>
                                        <div><dt>Total</dt><dd class="fw-bold text-vg"><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></dd></div>
                                    </dl>
                                </section>
                            </div>

                            <!-- Mise à jour du statut -->
                            <div class="col-12 col-lg-7">
                                <div class="commande-action-stack">
                                    <section class="commande-section">
                                        <h3 class="h6 fw-bold">Statut de la commande</h3>
                                        <form method="POST" action="/employe/commande/statut">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                                            <input type="hidden" name="action" value="changer_statut">

                                            <div class="commande-status-form">
                                                <div>
                                                    <label for="statut-<?= $cmd['commande_id'] ?>" class="form-label form-label-sm">Nouveau statut</label>
                                                    <select
                                                        class="form-select form-select-sm"
                                                        id="statut-<?= $cmd['commande_id'] ?>"
                                                        name="statut"
                                                        aria-label="Sélectionner le nouveau statut"
                                                        <?= empty($statutsDisponibles) ? 'disabled' : '' ?>
                                                    >
                                                        <?php foreach ($statutsDisponibles as $s): ?>
                                                            <option value="<?= sanitize($s) ?>" <?= ($cmd['statut'] ?? '') === $s ? 'selected' : '' ?>>
                                                                <?= sanitize(commandeStatusLabel($s)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php if (empty($statutsDisponibles)): ?>
                                                        <div class="form-text">Aucun changement de statut disponible.</div>
                                                    <?php endif; ?>
                                                </div>

                                                <div>
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

                                                <button type="submit" class="btn btn-vg btn-sm" aria-label="Mettre à jour le statut de la commande" <?= empty($statutsDisponibles) ? 'disabled' : '' ?>>
                                                    <i class="bi bi-check-lg me-1"></i>Mettre à jour
                                                </button>
                                            </div>
                                        </form>
                                    </section>

                                    <!-- Annulation si la commande est encore modifiable -->
                                    <?php if ($peutAnnuler): ?>
                                    <section class="commande-danger-zone">
                                        <div class="commande-danger-header">
                                            <div>
                                                <h3 class="h6 fw-bold mb-1">Annulation</h3>
                                                <p class="small mb-0">Action sensible avec motif obligatoire.</p>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#annulation-<?= (int)$cmd['commande_id'] ?>"
                                                aria-expanded="false"
                                                aria-controls="annulation-<?= (int)$cmd['commande_id'] ?>"
                                            >
                                                Annuler cette commande
                                            </button>
                                        </div>

                                        <div class="collapse" id="annulation-<?= (int)$cmd['commande_id'] ?>">
                                            <form
                                                method="POST"
                                                action="/employe/commande/statut"
                                                class="commande-cancel-form form-confirm"
                                                data-confirm="Confirmer l'annulation définitive de cette commande ?"
                                            >
                                                <?= csrfField() ?>
                                                <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                                                <input type="hidden" name="action" value="annuler">
                                                <input type="hidden" name="statut" value="<?= sanitize(commandeCancelledStatus()) ?>">

                                                <div>
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

                                                <div>
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

                                                <div class="form-check commande-cancel-confirm">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        id="confirm-annulation-<?= $cmd['commande_id'] ?>"
                                                        name="confirmation_annulation"
                                                        value="1"
                                                        required
                                                        aria-required="true"
                                                    >
                                                    <label class="form-check-label small" for="confirm-annulation-<?= $cmd['commande_id'] ?>">
                                                        Je confirme que cette annulation est volontaire et que le client sera informé.
                                                    </label>
                                                </div>

                                                <button type="submit" class="btn btn-danger btn-sm" aria-label="Confirmer l'annulation de la commande">
                                                    <i class="bi bi-x-circle me-1"></i>Confirmer l'annulation
                                                </button>
                                            </form>
                                        </div>
                                    </section>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div><!-- /row -->
                    </div><!-- /accordion-body -->
                </div>
            </div><!-- /accordion-item -->
            <?php endforeach; ?>
        </div><!-- /accordion -->
    <?php endif; ?>

</div>
