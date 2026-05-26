<?php
// src/views/pages/employe/commandes.php
$pageTitle = 'Gestion des commandes - Vite & Gourmand';
$statutsMiseAJour = array_values(array_filter(
    $statuts,
    fn($statut) => $statut !== commandeCancelledStatus()
));
$buildFilterUrl = static function (array $overrides = []) use ($filters): string {
    $params = array_merge($filters, $overrides);
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    return '/employe/commandes' . ($params ? '?' . http_build_query($params) : '');
};
$activeAdvancedFilters = !empty($filters['date_debut'])
    || !empty($filters['date_fin'])
    || !empty($filters['menu_id'])
    || !empty($filters['ville'])
    || !empty($filters['montant']);
?>
<div class="container py-5 commandes-page">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-list-check', 'title' => 'Gestion des commandes']); ?>

    <!-- Formulaire de filtres -->
    <div class="filtres-panel commandes-filter-panel p-3 mb-4">
        <nav class="commande-status-filters mb-3" aria-label="Filtrer par statut">
            <a
                href="<?= sanitize($buildFilterUrl(['statut' => ''])) ?>"
                class="commande-status-filter <?= empty($filters['statut']) ? 'is-active' : '' ?>"
                <?= empty($filters['statut']) ? 'aria-current="page"' : '' ?>
            >
                <span>Toutes</span>
                <strong><?= array_sum($statusCounts ?? []) ?></strong>
            </a>
            <?php foreach ($statuts as $s): ?>
                <a
                    href="<?= sanitize($buildFilterUrl(['statut' => $s])) ?>"
                    class="commande-status-filter <?= ($filters['statut'] ?? '') === $s ? 'is-active' : '' ?>"
                    <?= ($filters['statut'] ?? '') === $s ? 'aria-current="page"' : '' ?>
                >
                    <span><?= sanitize(commandeStatusLabel($s)) ?></span>
                    <strong><?= (int)($statusCounts[$s] ?? 0) ?></strong>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="GET" action="/employe/commandes" class="commande-filter-form" role="search" aria-label="Filtrer les commandes">
            <input type="hidden" name="statut" value="<?= sanitize($filters['statut'] ?? '') ?>">

            <div class="commande-filter-field commande-filter-search">
                <label for="filtre-q" class="form-label form-label-sm">Recherche globale</label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="filtre-q"
                    name="q"
                    value="<?= sanitize($filters['q'] ?? '') ?>"
                    placeholder="Client, email, téléphone, commande..."
                    aria-label="Rechercher une commande"
                >
            </div>

            <div class="commande-filter-field">
                <label for="filtre-periode" class="form-label form-label-sm">Période</label>
                <select class="form-select form-select-sm" id="filtre-periode" name="periode" aria-label="Filtrer par période">
                    <?php
                    $periodes = [
                        '' => 'Toutes les dates',
                        'today' => "Aujourd'hui",
                        'tomorrow' => 'Demain',
                        'week' => '7 prochains jours',
                        'upcoming' => 'À venir',
                        'past' => 'Passées',
                        'custom' => 'Dates personnalisées',
                    ];
                    ?>
                    <?php foreach ($periodes as $value => $label): ?>
                        <option value="<?= sanitize($value) ?>" <?= ($filters['periode'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= sanitize($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="commande-filter-field">
                <label for="filtre-tri" class="form-label form-label-sm">Tri</label>
                <select class="form-select form-select-sm" id="filtre-tri" name="tri" aria-label="Trier les commandes">
                    <?php
                    $tris = [
                        'date_prestation_asc' => 'Prestation proche',
                        'date_prestation_desc' => 'Prestation éloignée',
                        'commande_recente' => 'Plus récentes',
                        'montant_desc' => 'Montant décroissant',
                        'montant_asc' => 'Montant croissant',
                        'client_asc' => 'Client A-Z',
                    ];
                    ?>
                    <?php foreach ($tris as $value => $label): ?>
                        <option value="<?= sanitize($value) ?>" <?= ($filters['tri'] ?? 'date_prestation_asc') === $value ? 'selected' : '' ?>>
                            <?= sanitize($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="commande-filter-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#filtres-avances-commandes" aria-expanded="<?= $activeAdvancedFilters ? 'true' : 'false' ?>" aria-controls="filtres-avances-commandes">
                    <i class="bi bi-sliders me-1"></i>Avancés
                </button>
                <button type="submit" class="btn btn-vg btn-sm" aria-label="Appliquer les filtres">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
                <a href="/employe/commandes" class="btn btn-outline-secondary btn-sm btn-reset-filters">
                    Réinitialiser
                </a>
            </div>

            <div class="collapse commande-advanced-filters <?= $activeAdvancedFilters ? 'show' : '' ?>" id="filtres-avances-commandes">
                <div class="commande-advanced-grid">
                    <div>
                        <label for="filtre-date-debut" class="form-label form-label-sm">Du</label>
                        <input type="date" class="form-control form-control-sm" id="filtre-date-debut" name="date_debut" value="<?= sanitize($filters['date_debut'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="filtre-date-fin" class="form-label form-label-sm">Au</label>
                        <input type="date" class="form-control form-control-sm" id="filtre-date-fin" name="date_fin" value="<?= sanitize($filters['date_fin'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="filtre-menu" class="form-label form-label-sm">Menu</label>
                        <select class="form-select form-select-sm" id="filtre-menu" name="menu_id" aria-label="Filtrer par menu">
                            <option value="">Tous les menus</option>
                            <?php foreach ($menus as $menu): ?>
                                <option value="<?= (int)$menu['menu_id'] ?>" <?= (string)($filters['menu_id'] ?? '') === (string)$menu['menu_id'] ? 'selected' : '' ?>>
                                    <?= sanitize($menu['titre'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="filtre-ville" class="form-label form-label-sm">Ville</label>
                        <input type="text" class="form-control form-control-sm" id="filtre-ville" name="ville" value="<?= sanitize($filters['ville'] ?? '') ?>" placeholder="Bordeaux, Pessac...">
                    </div>
                    <div>
                        <label for="filtre-montant" class="form-label form-label-sm">Montant</label>
                        <select class="form-select form-select-sm" id="filtre-montant" name="montant" aria-label="Filtrer par montant">
                            <option value="">Tous les montants</option>
                            <option value="moins_250" <?= ($filters['montant'] ?? '') === 'moins_250' ? 'selected' : '' ?>>Moins de 250 €</option>
                            <option value="250_1000" <?= ($filters['montant'] ?? '') === '250_1000' ? 'selected' : '' ?>>250 à 1 000 €</option>
                            <option value="plus_1000" <?= ($filters['montant'] ?? '') === 'plus_1000' ? 'selected' : '' ?>>Plus de 1 000 €</option>
                        </select>
                    </div>
                </div>
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
                $statutsDisponibles = $statutsMiseAJour;
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
