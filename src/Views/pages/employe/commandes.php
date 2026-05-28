<?php
// src/views/pages/employe/commandes.php
$pageTitle = buildPageTitle('Gestion des commandes');
$statutsMiseAJour = array_values(array_filter(
    $statuts,
    fn($statut) => $statut !== commandeCancelledStatus()
));
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
        <form method="GET" action="/employe/commandes" class="commande-filter-form" role="search" aria-label="Filtrer les commandes">
            <div class="commande-filter-field commande-filter-status">
                <label for="filtre-statut" class="form-label form-label-sm">Statut</label>
                <select class="form-select form-select-sm" id="filtre-statut" name="statut" aria-label="Filtrer par statut">
                    <option value="">Tous les statuts (<?= array_sum($statusCounts ?? []) ?>)</option>
                    <?php foreach ($statuts as $s): ?>
                        <option value="<?= sanitize($s) ?>" <?= ($filters['statut'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= sanitize(commandeStatusLabel($s)) ?> (<?= (int)($statusCounts[$s] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

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

            <div class="commande-filter-field commande-filter-period">
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

            <div class="commande-filter-field commande-filter-sort">
                <label for="filtre-tri" class="form-label form-label-sm">Tri</label>
                <select class="form-select form-select-sm" id="filtre-tri" name="tri" aria-label="Trier les commandes">
                    <?php
                    $tris = [
                        'date_prestation_desc' => 'Prestation récente',
                        'date_prestation_asc' => 'Prestation proche',
                        'commande_recente' => 'Plus récentes',
                        'montant_desc' => 'Montant décroissant',
                        'montant_asc' => 'Montant croissant',
                        'client_asc' => 'Client A-Z',
                    ];
                    ?>
                    <?php foreach ($tris as $value => $label): ?>
                        <option value="<?= sanitize($value) ?>" <?= ($filters['tri'] ?? 'date_prestation_desc') === $value ? 'selected' : '' ?>>
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

    <!-- Toggle vue Liste / Calendrier -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <div class="btn-group" role="group" aria-label="Mode d'affichage">
            <button type="button" class="btn btn-sm btn-vg active" id="btn-vue-liste" aria-pressed="true">
                <i class="bi bi-list-ul me-1"></i>Liste
            </button>
            <button type="button" class="btn btn-sm btn-vg-outline" id="btn-vue-calendrier" aria-pressed="false">
                <i class="bi bi-calendar3 me-1"></i>Calendrier
            </button>
        </div>
        <span class="text-muted small" id="vue-label-count"><?= count($commandes) ?> commande<?= count($commandes) > 1 ? 's' : '' ?></span>
    </div>

    <!-- Vue Calendrier -->
    <div id="vue-calendrier" class="mb-4" style="display:none">
        <div id="fullcalendar" style="background:#fff;padding:16px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08)"></div>
    </div>

    <!-- Vue Liste -->
    <div id="vue-liste">
    <!-- Tableau commandes -->
    <?php if (empty($commandes)): ?>
        <div class="alert alert-info">Aucune commande ne correspond aux critères.</div>
    <?php else: ?>
        <div class="accordion commandes-accordion" id="accordionCommandes">
            <?php foreach ($commandes as $idx => $cmd): ?>
            <?php
                $statutActuel = $cmd['statut'] ?? null;
                $statutsDisponibles = $statutsMiseAJour;
                $commandeId = (int)$cmd['commande_id'];
                $lignesCommande    = $lignesByCommande[$commandeId] ?? [];
                $documentsCommande = $documentsByCommande[$commandeId] ?? [];
                $paiementsSynthese = $paiementsByCommande[$commandeId] ?? null;
                $totalEncaisse     = (float)($paiementsSynthese['total_encaisse'] ?? 0);
                $prixTotal         = (float)($cmd['prix_total'] ?? 0);
                $soldeRestant      = max(0, round($prixTotal - $totalEncaisse, 2));
                $statutPaiement    = \App\Models\PaiementModel::statutPaiement($totalEncaisse, $prixTotal);
                $peutAnnuler = $statutActuel !== commandeCancelledStatus()
                    && commandeCanTransition($statutActuel, commandeCancelledStatus());
            ?>
            <div class="accordion-item commande-card mb-3" id="cmd-<?= $commandeId ?>">
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
                            <span class="commande-status"><?= paiementStatusBadge($statutPaiement) ?></span>
                        </div>
                    </button>
                </h2>

                <div id="collapse<?= $cmd['commande_id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $cmd['commande_id'] ?>" data-bs-parent="#accordionCommandes">
                    <div class="accordion-body commande-card-body">
                        <div class="row g-4 align-items-start">

                            <!-- Informations de la commande -->
                            <div class="col-12 col-lg-5">
                                <section class="commande-section">
                                    <h3 class="h6 fw-bold">Client</h3>
                                    <dl class="commande-detail-list mb-0 small">
                                        <div><dt>Client</dt><dd><?= sanitize(personFullName($cmd)) ?></dd></div>
                                        <div><dt>Email</dt><dd class="text-break"><?= sanitize($cmd['email'] ?? '') ?></dd></div>
                                        <div><dt>Téléphone</dt><dd><?= sanitize($cmd['telephone'] ?? '—') ?></dd></div>
                                        <div><dt>Adresse</dt><dd><?= sanitize(($cmd['adresse_livraison'] ?? '') . ', ' . ($cmd['code_postal_livraison'] ?? '') . ' ' . ($cmd['ville_livraison'] ?? '')) ?></dd></div>
                                    </dl>
                                </section>

                                <section class="commande-section mt-3">
                                    <h3 class="h6 fw-bold">Prestation</h3>
                                    <dl class="commande-detail-list mb-0 small">
                                        <div><dt>Date</dt><dd><?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></dd></div>
                                        <div><dt>Heure</dt><dd><?= sanitize($cmd['heure_livraison'] ?? '—') ?></dd></div>
                                        <div><dt>Ville</dt><dd><?= sanitize($cmd['ville_livraison'] ?? '—') ?></dd></div>
                                        <div><dt>Commande</dt><dd><code><?= sanitize($cmd['numero_commande'] ?? '') ?></code></dd></div>
                                    </dl>
                                    <?php if (!empty($cmd['instructions'])): ?>
                                    <div class="mt-2 p-2 rounded small" style="background:var(--vg-creme);border-left:3px solid var(--vg-or)">
                                        <strong><i class="bi bi-chat-left-text me-1"></i>Remarques client :</strong>
                                        <?= nl2br(sanitize($cmd['instructions'])) ?>
                                    </div>
                                    <?php endif ?>
                                </section>

                                <section class="commande-section mt-3">
                                    <h3 class="h6 fw-bold">Documents</h3>
                                    <div class="commande-doc-actions">
                                        <form method="POST" action="/employe/document/creer">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="commande_id" value="<?= $commandeId ?>">
                                            <input type="hidden" name="type_document" value="devis">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-file-earmark-plus me-1"></i>Devis
                                            </button>
                                        </form>
                                        <form method="POST" action="/employe/document/creer">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="commande_id" value="<?= $commandeId ?>">
                                            <input type="hidden" name="type_document" value="acompte">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-file-earmark-check me-1"></i>Acompte
                                            </button>
                                        </form>
                                        <form method="POST" action="/employe/document/creer">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="commande_id" value="<?= $commandeId ?>">
                                            <input type="hidden" name="type_document" value="facture">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-file-earmark-text me-1"></i>Facture
                                            </button>
                                        </form>
                                        <form method="POST" action="/employe/document/creer">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="commande_id" value="<?= $commandeId ?>">
                                            <input type="hidden" name="type_document" value="ticket">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-receipt me-1"></i>Ticket
                                            </button>
                                        </form>
                                    </div>

                                    <?php if (empty($documentsCommande)): ?>
                                        <p class="small text-muted mb-0 mt-2">Aucun brouillon généré.</p>
                                    <?php else: ?>
                                        <div class="commande-doc-list mt-2">
                                            <?php foreach ($documentsCommande as $doc): ?>
                                                <?php
                                                $docType  = $doc['type_document'] ?? 'document';
                                                $docLabel = match ($docType) {
                                                    'ticket'  => 'Ticket',
                                                    'devis'   => 'Devis',
                                                    'acompte' => 'Acompte',
                                                    default   => 'Facture',
                                                };
                                                $docIcon  = match ($docType) {
                                                    'ticket'  => 'bi-receipt',
                                                    'devis'   => 'bi-file-earmark-plus',
                                                    'acompte' => 'bi-file-earmark-check',
                                                    default   => 'bi-file-earmark-text',
                                                };
                                                $docStatut = $doc['statut'] ?? 'brouillon';
                                                $docStatutLabel = $docStatut === 'finalise' ? 'Finalisé' : ucfirst((string)$docStatut);
                                                ?>
                                                <article class="commande-doc-item">
                                                    <div class="commande-doc-top">
                                                        <span class="commande-doc-type">
                                                            <i class="bi <?= sanitize($docIcon) ?>" aria-hidden="true"></i>
                                                            <?= sanitize($docLabel) ?>
                                                        </span>
                                                        <?php if (!empty($doc['sent_at'])): ?>
                                                            <span class="commande-doc-state-icons">
                                                                <i class="bi bi-envelope-check text-success" title="Envoyé" aria-label="Envoyé"></i>
                                                            </span>
                                                        <?php elseif (!empty($doc['archive_path'])): ?>
                                                            <span class="commande-doc-state-icons">
                                                                <i class="bi bi-archive text-muted" title="Archivé" aria-label="Archivé"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="commande-doc-status"><?= sanitize($docStatutLabel) ?></span>
                                                    </div>
                                                    <div class="commande-doc-bottom">
                                                        <strong class="commande-doc-price"><?= sanitize(formatPrice($doc['total_ttc'] ?? 0)) ?></strong>
                                                        <span class="commande-doc-icon-actions">
                                                            <a
                                                                href="/employe/document/apercu?id=<?= (int)$doc['document_id'] ?>"
                                                                class="commande-doc-icon-btn"
                                                                aria-label="Aperçu <?= sanitize(strtolower($docLabel)) ?>"
                                                                title="Aperçu"
                                                            >
                                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                                            </a>
                                                            <a
                                                                href="/employe/document/edit?id=<?= (int)$doc['document_id'] ?>"
                                                                class="commande-doc-icon-btn"
                                                                aria-label="Éditer <?= sanitize(strtolower($docLabel)) ?>"
                                                                title="Éditer"
                                                            >
                                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                            </a>
                                                        </span>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            </div>

                            <!-- Mise à jour du statut -->
                            <div class="col-12 col-lg-7">
                                <div class="commande-action-stack">
                                    <section class="commande-section">
                                        <h3 class="h6 fw-bold">Lignes de commande</h3>
                                        <div class="commande-lines-table">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Menu</th>
                                                        <th class="text-end">Pers.</th>
                                                        <th class="text-end">Brut</th>
                                                        <th class="text-end">Remise</th>
                                                        <th class="text-end">Net menu</th>
                                                        <th class="text-end">Livraison</th>
                                                        <th class="text-end">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($lignesCommande as $ligne): ?>
                                                        <?php
                                                            $nbPersonnes = (int)($ligne['nombre_personne'] ?? 0);
                                                            $brutMenu = !empty($ligne['prix_par_personne'])
                                                                ? round((float)$ligne['prix_par_personne'] * $nbPersonnes, 2)
                                                                : (float)($ligne['prix_menu'] ?? 0);
                                                            $remiseMenu = max(0, $brutMenu - (float)($ligne['prix_menu'] ?? 0));
                                                        ?>
                                                        <tr>
                                                            <td data-label="Menu"><?= sanitize($ligne['menu_titre'] ?? '') ?></td>
                                                            <td data-label="Pers." class="text-end"><?= $nbPersonnes ?></td>
                                                            <td data-label="Brut" class="text-end text-nowrap"><?= sanitize(formatPrice($brutMenu)) ?></td>
                                                            <td data-label="Remise" class="text-end text-nowrap text-success"><?= $remiseMenu > 0 ? '-' . sanitize(formatPrice($remiseMenu)) : '—' ?></td>
                                                            <td data-label="Net menu" class="text-end text-nowrap"><?= sanitize(formatPrice($ligne['prix_menu'] ?? 0)) ?></td>
                                                            <td data-label="Livraison" class="text-end text-nowrap"><?= sanitize(formatPrice($ligne['prix_livraison'] ?? 0)) ?></td>
                                                            <td data-label="Total" class="text-end text-nowrap fw-semibold"><?= sanitize(formatPrice($ligne['prix_total_ligne'] ?? 0)) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="6" class="text-end commande-total-label">Total commande</th>
                                                        <th class="text-end text-nowrap text-vg commande-total-value"><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </section>

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

                                    <!-- Paiements -->
                                    <section class="commande-section">
                                        <h3 class="h6 fw-bold d-flex align-items-center gap-2">
                                            Paiements
                                            <?= paiementStatusBadge($statutPaiement) ?>
                                        </h3>

                                        <!-- Synthèse encaissement -->
                                        <div class="commande-paiement-synthese mb-3">
                                            <div class="commande-paiement-row">
                                                <span class="text-muted small">Total commande</span>
                                                <strong><?= sanitize(formatPrice($prixTotal)) ?></strong>
                                            </div>
                                            <div class="commande-paiement-row">
                                                <span class="text-muted small">Encaissé</span>
                                                <strong class="text-success"><?= sanitize(formatPrice($totalEncaisse)) ?></strong>
                                            </div>
                                            <?php if ($soldeRestant > 0): ?>
                                            <div class="commande-paiement-row">
                                                <span class="text-muted small">Solde restant</span>
                                                <strong class="text-vg"><?= sanitize(formatPrice($soldeRestant)) ?></strong>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Historique des paiements -->
                                        <?php
                                        $histoPaiements = isset($paiementsHistorique) ? ($paiementsHistorique[$commandeId] ?? []) : [];
                                        ?>
                                        <?php if (!empty($histoPaiements)): ?>
                                        <div class="commande-paiement-historique mb-3">
                                            <?php foreach ($histoPaiements as $p): ?>
                                            <div class="commande-paiement-item">
                                                <div class="commande-paiement-item-info">
                                                    <span class="badge bg-secondary me-1">
                                                        <?= sanitize(paiementTypeLabel($p['type_paiement'] ?? '')) ?>
                                                    </span>
                                                    <span class="small text-muted"><?= sanitize(formatDateFr($p['date_paiement'] ?? null)) ?></span>
                                                    <?php if (!empty($p['mode'])): ?>
                                                    <span class="small text-muted">· <?= sanitize($p['mode']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($p['reference'])): ?>
                                                    <span class="small text-muted">· Réf. <?= sanitize($p['reference']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="commande-paiement-item-actions">
                                                    <strong class="small"><?= sanitize(formatPrice($p['montant'] ?? 0)) ?></strong>
                                                    <form method="POST" action="/employe/paiement/supprimer" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="paiement_id" value="<?= (int)$p['paiement_id'] ?>">
                                                        <input type="hidden" name="commande_id" value="<?= $commandeId ?>">
                                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0 ms-2"
                                                                data-confirm="Supprimer ce paiement ?"
                                                                aria-label="Supprimer ce paiement">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Formulaire enregistrement paiement -->
                                        <?php if ($statutPaiement !== 'solde'): ?>
                                        <details class="commande-paiement-form-toggle">
                                            <summary class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-plus-circle me-1"></i>Enregistrer un paiement
                                            </summary>
                                            <form method="POST" action="/employe/paiement/enregistrer" class="commande-paiement-form mt-3">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="commande_id" value="<?= $commandeId ?>">
                                                <div class="row g-2">
                                                    <div class="col-6 col-lg-4">
                                                        <label class="form-label form-label-sm">Type</label>
                                                        <select class="form-select form-select-sm" name="type_paiement" required>
                                                            <option value="acompte">Acompte</option>
                                                            <option value="solde">Solde</option>
                                                            <option value="paiement_unique" <?= $statutPaiement === 'non_paye' ? 'selected' : '' ?>>Paiement unique</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-lg-4">
                                                        <label class="form-label form-label-sm">Mode</label>
                                                        <select class="form-select form-select-sm" name="mode" required>
                                                            <?php foreach ($modesPaiement as $mp): ?>
                                                            <option value="<?= sanitize($mp['code']) ?>"><?= sanitize($mp['libelle']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-lg-4">
                                                        <label class="form-label form-label-sm">Montant (€)</label>
                                                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm"
                                                               name="montant"
                                                               value="<?= $soldeRestant > 0 ? sanitize(formatPriceInput($soldeRestant)) : '' ?>"
                                                               required>
                                                    </div>
                                                    <div class="col-6 col-lg-4">
                                                        <label class="form-label form-label-sm">Date</label>
                                                        <input type="date" class="form-control form-control-sm"
                                                               name="date_paiement"
                                                               value="<?= date('Y-m-d') ?>"
                                                               required>
                                                    </div>
                                                    <div class="col-12 col-lg-8">
                                                        <label class="form-label form-label-sm">Référence (optionnel)</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                               name="reference"
                                                               placeholder="N° virement, chèque...">
                                                    </div>
                                                    <div class="col-12 text-end">
                                                        <button type="submit" class="btn btn-vg btn-sm">
                                                            <i class="bi bi-check-lg me-1"></i>Enregistrer
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </details>
                                        <?php endif; ?>
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
    </div><!-- /#vue-liste -->

</div>

<!-- FullCalendar -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" nonce="<?= cspNonce() ?>">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" nonce="<?= cspNonce() ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/fr.global.min.js" nonce="<?= cspNonce() ?>"></script>

<script nonce="<?= cspNonce() ?>">
(function () {
    const btnListe      = document.getElementById('btn-vue-liste');
    const btnCal        = document.getElementById('btn-vue-calendrier');
    const vueListe      = document.getElementById('vue-liste');
    const vueCal        = document.getElementById('vue-calendrier');
    const labelCount    = document.getElementById('vue-label-count');

    let calInstance = null;

    const statutColors = {
        en_attente:     '#f59e0b',
        accepte:        '#10b981',
        en_preparation: '#3b82f6',
        livre:          '#6366f1',
        annule:         '#ef4444',
        termine:        '#6b7280',
    };

    function initCalendar() {
        if (calInstance) return;
        const el = document.getElementById('fullcalendar');
        calInstance = new FullCalendar.Calendar(el, {
            locale: 'fr',
            initialView: 'dayGridMonth',
            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'dayGridMonth,timeGridWeek,listWeek',
            },
            height: 'auto',
            events: {
                url: '/employe/commandes/calendrier',
                method: 'GET',
                failure: () => { labelCount.textContent = 'Erreur de chargement'; },
            },
            eventClick: function (info) {
                const p = info.event.extendedProps;
                const url = '/employe/commandes#commande-' + p.commande_id;
                btnListe.click();
                window.location.hash = '#commande-' + p.commande_id;
            },
            eventDidMount: function (info) {
                const p = info.event.extendedProps;
                info.el.title =
                    info.event.title + '\n' +
                    'Heure : ' + (p.heure || '—') + '\n' +
                    'Menu : ' + (p.menu || '—') + '\n' +
                    'Total : ' + new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(p.prix);
            },
            loading: function (isLoading) {
                labelCount.textContent = isLoading ? 'Chargement…' : '';
            },
            eventsSet: function (events) {
                labelCount.textContent = events.length + ' commande' + (events.length > 1 ? 's' : '');
            },
            noEventsContent: 'Aucune commande sur cette période.',
        });
        calInstance.render();
    }

    btnCal.addEventListener('click', function () {
        vueListe.style.display  = 'none';
        vueCal.style.display    = '';
        btnCal.classList.add('active');
        btnCal.setAttribute('aria-pressed', 'true');
        btnListe.classList.remove('active');
        btnListe.setAttribute('aria-pressed', 'false');
        initCalendar();
        localStorage.setItem('commandes_vue', 'calendrier');
    });

    btnListe.addEventListener('click', function () {
        vueCal.style.display    = 'none';
        vueListe.style.display  = '';
        btnListe.classList.add('active');
        btnListe.setAttribute('aria-pressed', 'true');
        btnCal.classList.remove('active');
        btnCal.setAttribute('aria-pressed', 'false');
        localStorage.setItem('commandes_vue', 'liste');
    });

    // Restaurer la dernière vue
    if (localStorage.getItem('commandes_vue') === 'calendrier') {
        btnCal.click();
    }
})();
</script>
