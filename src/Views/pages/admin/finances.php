<?php
$pageTitle   = buildPageTitle('Finances');
$cspNonce    = $GLOBALS['csp_nonce'] ?? '';
$isAssujetti = ($regimeTva ?? 'assujetti') === 'assujetti';

$totalTtcCents = (int)($synthese['total_ttc_cents'] ?? 0);
$totalHtCents = (int)($synthese['total_ht_cents'] ?? 0);
$totalTvaCents = (int)($synthese['total_tva_cents'] ?? 0);
$totalNb = (int)($synthese['nb_commandes'] ?? 0);
$encaisseCents = (int)($synthese['montant_encaisse_cents'] ?? 0);
$soldeRestantCents = (int)($synthese['solde_restant_cents'] ?? 0);
$panierMoyenCents = $totalNb > 0 ? (int) round($totalTtcCents / $totalNb) : 0;

$menuSalesTtc = array_sum(array_map(fn($row) => (int)($row['ca_cents'] ?? 0), $caStats ?? []));
$menuSalesHt  = array_sum(array_map(fn($row) => (int)($row['ca_ht_cents'] ?? 0), $caStats ?? []));
$menuSalesTva = $menuSalesTtc - $menuSalesHt;
$topMenu      = $caStats[0] ?? null;
$topMenuShare = ($topMenu && $menuSalesTtc > 0)
    ? ((int)$topMenu['ca_cents'] / $menuSalesTtc) * 100
    : 0;

$activeFilters = (int)($menuFilter ?? 0) > 0 || !empty($dateDebut) || !empty($dateFin);
$periodLabel = 'Toutes les commandes acceptées';
if (!empty($dateDebut) && !empty($dateFin)) {
    $periodLabel = 'Du ' . formatDateFr($dateDebut) . ' au ' . formatDateFr($dateFin);
} elseif (!empty($dateDebut)) {
    $periodLabel = 'Depuis le ' . formatDateFr($dateDebut);
} elseif (!empty($dateFin)) {
    $periodLabel = "Jusqu'au " . formatDateFr($dateFin);
}

$chartLabels = array_map(fn($row) => $row['titre'] ?? '', $caStats ?? []);
$chartData = array_map(fn($row) => ((int)($row['ca_cents'] ?? 0)) / 100, $caStats ?? []);
$mensuelAsc = array_reverse($caMensuel ?? []);
$chartMensuelLabels = array_map(fn($row) => $row['annee_mois'] ?? '', $mensuelAsc);
$chartMensuelData = array_map(fn($row) => ((int)($row['ca_ttc_cents'] ?? 0)) / 100, $mensuelAsc);

$exportStatsUrl = '/admin/stats/export?' . http_build_query(array_filter([
    'date_debut' => $dateDebut ?? '',
    'date_fin'   => $dateFin ?? '',
]));

$siret = trim((string)($config['entreprise_siret'] ?? ''));
$activeTab = $_GET['tab'] ?? 'stats';
if (!in_array($activeTab, ['stats', 'comptabilite', 'marges'], true)) {
    $activeTab = 'stats';
}

$nbCouts = count($coutsMatiere ?? []);
$coutMoyen = $nbCouts > 0
    ? array_sum(array_map(fn($row) => (float)($row['cout_matiere_portion'] ?? 0), $coutsMatiere)) / $nbCouts
    : 0;
$coutMax = $nbCouts > 0
    ? max(array_map(fn($row) => (float)($row['cout_matiere_portion'] ?? 0), $coutsMatiere))
    : 0;
$nbUtilises = count(array_filter($coutsMatiere ?? [], fn($row) => (int)($row['nb_menus_actifs'] ?? 0) > 0));
?>

<?php partial('partials/page_title_bar', ['icon' => 'bi-graph-up', 'title' => 'Finances']); ?>

<ul class="nav nav-tabs mb-4" id="financesTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'stats' ? 'active' : '' ?>"
                id="tab-stats" data-bs-toggle="tab" data-bs-target="#pane-stats" type="button" role="tab">
            <i class="bi bi-bar-chart me-1"></i>Statistiques CA
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'comptabilite' ? 'active' : '' ?>"
                id="tab-comptabilite" data-bs-toggle="tab" data-bs-target="#pane-comptabilite" type="button" role="tab">
            <i class="bi bi-archive me-1"></i>Comptabilité
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'marges' ? 'active' : '' ?>"
                id="tab-marges" data-bs-toggle="tab" data-bs-target="#pane-marges" type="button" role="tab">
            <i class="bi bi-basket me-1"></i>Coûts matière
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade <?= $activeTab === 'stats' ? 'show active' : '' ?>" id="pane-stats" role="tabpanel">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <p class="text-muted mb-1">CA commandes : total TTC des commandes acceptées ou au-delà, livraison incluse.</p>
                <p class="text-muted small mb-0">Ventes menus : montant net des menus, hors livraison. Les parts par menu utilisent uniquement ce sous-total.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="stats-period-badge">
                    <i class="bi bi-calendar3 me-1"></i><?= sanitize($periodLabel) ?>
                </span>
                <a href="<?= sanitize($exportStatsUrl) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
        </div>

        <section class="stats-kpi-grid mb-4" aria-label="Indicateurs de synthèse">
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">CA commandes TTC</span>
                <strong class="stats-kpi-value"><?= sanitize(formatMoneyCents($totalTtcCents)) ?></strong>
                <?php if ($isAssujetti): ?>
                    <span class="stats-kpi-note">HT : <?= sanitize(formatMoneyCents($totalHtCents)) ?> · TVA : <?= sanitize(formatMoneyCents($totalTvaCents)) ?></span>
                <?php else: ?>
                    <span class="stats-kpi-note">Livraison incluse</span>
                <?php endif; ?>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Ventes menus TTC</span>
                <strong class="stats-kpi-value"><?= sanitize(formatMoneyCents($menuSalesTtc)) ?></strong>
                <span class="stats-kpi-note">Hors livraison</span>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Commandes</span>
                <strong class="stats-kpi-value"><?= sanitize(formatInteger($totalNb)) ?></strong>
                <span class="stats-kpi-note">Panier moyen commande <?= sanitize(formatMoneyCents($panierMoyenCents)) ?></span>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Meilleur menu</span>
                <strong class="stats-kpi-value stats-kpi-value--text"><?= sanitize($topMenu['titre'] ?? 'Aucun') ?></strong>
                <span class="stats-kpi-note">
                    <?= $topMenu ? sanitize(number_format($topMenuShare, 0, ',', ' ') . ' % des ventes menus') : 'Aucune donnée' ?>
                </span>
            </article>
        </section>

        <section class="stats-filter-panel mb-4" aria-label="Filtres">
            <form method="GET" action="/admin/stats" class="row g-3 align-items-end" role="search">
                <input type="hidden" name="tab" value="stats">
                <div class="col-12 col-xl-4">
                    <label for="filtre-menu" class="form-label form-label-sm">Menu</label>
                    <select class="form-select form-select-sm" id="filtre-menu" name="menu_id">
                        <option value="">Tous les menus</option>
                        <?php foreach ($menus as $menu): ?>
                            <option value="<?= (int)$menu['menu_id'] ?>" <?= (int)($menuFilter ?? 0) === (int)$menu['menu_id'] ? 'selected' : '' ?>>
                                <?= sanitize($menu['titre'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label for="filtre-debut" class="form-label form-label-sm">Date début</label>
                    <input type="date" class="form-control form-control-sm" id="filtre-debut" name="date_debut" value="<?= sanitize($dateDebut ?? '') ?>">
                </div>
                <div class="col-12 col-lg-3">
                    <label for="filtre-fin" class="form-label form-label-sm">Date fin</label>
                    <input type="date" class="form-control form-control-sm" id="filtre-fin" name="date_fin" value="<?= sanitize($dateFin ?? '') ?>">
                </div>
                <div class="col-12 col-xl-2 d-flex gap-2">
                    <button type="submit" class="btn btn-brand btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                    <a href="/admin/stats" class="btn btn-outline-secondary btn-sm <?= $activeFilters ? '' : 'disabled' ?>">Réinitialiser</a>
                </div>
            </form>
        </section>

        <?php if (empty($caStats) && empty($caMensuel)): ?>
            <div class="stats-empty-state">
                <i class="bi bi-bar-chart"></i>
                <strong>Aucune donnée pour cette sélection</strong>
                <span>Essayez une période plus large ou retirez le filtre menu.</span>
            </div>
        <?php else: ?>
            <div class="row g-4 mb-4">
                <?php if (!empty($caStats)): ?>
                    <div class="col-12 <?= !empty($caMensuel) ? 'col-xl-6' : 'col-xl-8 offset-xl-2' ?>">
                        <section class="stats-panel h-100">
                            <div class="stats-panel-header">
                                <div><h2>Ventes menus TTC</h2><p>Hors frais de livraison, sur la période filtrée.</p></div>
                            </div>
                            <div class="stats-chart-wrap"><canvas id="chartCA" aria-label="Ventes menus TTC" role="img"></canvas></div>
                        </section>
                    </div>
                <?php endif; ?>
                <?php if (!empty($caMensuel)): ?>
                    <div class="col-12 <?= !empty($caStats) ? 'col-xl-6' : '' ?>">
                        <section class="stats-panel h-100">
                            <div class="stats-panel-header">
                                <div><h2>CA commandes mensuel</h2><p>Livraison incluse · <?= count($caMensuel) ?> mois.</p></div>
                            </div>
                            <div class="stats-chart-wrap"><canvas id="chartMensuel" aria-label="CA commandes mensuel" role="img"></canvas></div>
                        </section>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($caStats)): ?>
                <section class="stats-panel stats-detail-panel mb-4">
                    <div class="stats-panel-header stats-panel-header--table">
                        <div><h2>Détail des ventes menus</h2><p>Les colonnes CA ci-dessous excluent la livraison.</p></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table stats-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Menu</th>
                                    <th class="text-end">Commandes contenant le menu</th>
                                    <th class="text-end">Moy. menu / commande</th>
                                    <?php if ($isAssujetti): ?>
                                        <th class="text-end">Ventes HT</th>
                                        <th class="text-end">TVA</th>
                                    <?php endif; ?>
                                    <th class="text-end">Part ventes menus</th>
                                    <th class="text-end">Ventes TTC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($caStats as $row):
                                    $nb = (int)($row['nb'] ?? 0);
                                    $ca = (int)($row['ca_cents'] ?? 0);
                                    $caHT = (int)($row['ca_ht_cents'] ?? 0);
                                    $tva = $ca - $caHT;
                                    $average = $nb > 0 ? $ca / $nb : 0;
                                    $share = $menuSalesTtc > 0 ? ($ca / $menuSalesTtc) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?= sanitize($row['titre'] ?? '') ?></td>
                                        <td class="text-end"><?= sanitize(formatInteger($nb)) ?></td>
                                        <td class="text-end text-nowrap"><?= sanitize(formatMoneyCents((int) round($average))) ?></td>
                                        <?php if ($isAssujetti): ?>
                                            <td class="text-end text-nowrap"><?= sanitize(formatMoneyCents($caHT)) ?></td>
                                            <td class="text-end text-nowrap text-muted"><?= sanitize(formatMoneyCents($tva)) ?></td>
                                        <?php endif; ?>
                                        <td class="text-end"><?= sanitize(number_format($share, 0, ',', ' ')) ?> %</td>
                                        <td class="text-end fw-bold text-brand text-nowrap"><?= sanitize(formatMoneyCents($ca)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Ventes menus</td>
                                    <td class="text-end">—</td>
                                    <td class="text-end">—</td>
                                    <?php if ($isAssujetti): ?>
                                        <td class="text-end text-nowrap"><?= sanitize(formatMoneyCents($menuSalesHt)) ?></td>
                                        <td class="text-end text-nowrap text-muted"><?= sanitize(formatMoneyCents($menuSalesTva)) ?></td>
                                    <?php endif; ?>
                                    <td class="text-end">100 %</td>
                                    <td class="text-end fw-bold text-brand text-nowrap"><?= sanitize(formatMoneyCents($menuSalesTtc)) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($caMensuel)): ?>
                <section class="stats-panel stats-detail-panel">
                    <div class="stats-panel-header stats-panel-header--table">
                        <div><h2>Tendance mensuelle</h2><p>CA commandes par mois de comptabilisation, livraison incluse.</p></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table stats-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Mois</th>
                                    <th class="text-end">Commandes</th>
                                    <th class="text-end">Personnes</th>
                                    <th class="text-end">Panier moyen</th>
                                    <?php if ($isAssujetti): ?>
                                        <th class="text-end">CA HT</th>
                                        <th class="text-end">TVA</th>
                                    <?php endif; ?>
                                    <th class="text-end">CA TTC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($caMensuel as $mois): ?>
                                    <tr>
                                        <td><?= sanitize($mois['annee_mois'] ?? '') ?></td>
                                        <td class="text-end"><?= sanitize(formatInteger($mois['nb_commandes'] ?? 0)) ?></td>
                                        <td class="text-end"><?= sanitize(formatInteger($mois['nb_personnes'] ?? 0)) ?></td>
                                        <td class="text-end text-nowrap"><?= sanitize(formatPrice($mois['panier_moyen_ttc'] ?? 0)) ?></td>
                                        <?php if ($isAssujetti): ?>
                                            <td class="text-end text-nowrap"><?= sanitize(formatPrice($mois['ca_ht'] ?? 0)) ?></td>
                                            <td class="text-end text-nowrap text-muted"><?= sanitize(formatPrice($mois['tva_collectee'] ?? 0)) ?></td>
                                        <?php endif; ?>
                                        <td class="text-end fw-bold text-brand text-nowrap"><?= sanitize(formatPrice($mois['ca_ttc'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'comptabilite' ? 'show active' : '' ?>" id="pane-comptabilite" role="tabpanel">
        <p class="text-muted mb-4">
            Exports des données financières. Toutes les commandes au statut accepté ou ultérieur sont comptabilisées.
            <?php if (!$isAssujetti): ?>
                <span class="badge bg-secondary ms-1">Régime non-assujetti TVA (art. 293 B CGI)</span>
            <?php endif; ?>
        </p>

        <?php if (!$siret): ?>
            <div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                <div>
                    <strong>SIRET manquant.</strong>
                    Vérifiez les informations légales de l'entreprise avant d'utiliser les exports comme support comptable.
                    <a href="/admin/parametres?tab=entreprise" class="alert-link ms-1">Configurer l'entreprise →</a>
                </div>
            </div>
        <?php endif; ?>

        <section class="stats-kpi-grid mb-5" aria-label="Soldes globaux">
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">CA commandes TTC</span>
                <strong class="stats-kpi-value"><?= sanitize(formatMoneyCents($totalTtcCents)) ?></strong>
                <?php if ($isAssujetti): ?><span class="stats-kpi-note">HT : <?= sanitize(formatMoneyCents($totalHtCents)) ?></span><?php endif; ?>
            </article>
            <?php if ($isAssujetti): ?>
                <article class="stats-kpi-card">
                    <span class="stats-kpi-label">TVA calculée</span>
                    <strong class="stats-kpi-value"><?= sanitize(formatMoneyCents($totalTvaCents)) ?></strong>
                    <span class="stats-kpi-note">Selon les snapshots de taux des lignes</span>
                </article>
            <?php endif; ?>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Encaissé net</span>
                <strong class="stats-kpi-value"><?= sanitize(formatMoneyCents($encaisseCents)) ?></strong>
                <span class="stats-kpi-note">Remboursements déduits</span>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Solde restant</span>
                <strong class="stats-kpi-value"><?= sanitize(formatMoneyCents($soldeRestantCents)) ?></strong>
                <span class="stats-kpi-note"><?= sanitize(formatInteger($totalNb)) ?> commandes comptabilisées</span>
            </article>
        </section>

        <h2 class="h5 mb-4 fw-semibold">Exports CSV</h2>
        <div class="row g-4">
            <?php
            $exports = [
                ['format' => 'commandes', 'icon' => 'bi-file-earmark-spreadsheet', 'title' => 'Journal des commandes', 'description' => 'Une ligne par commande : dates, client, total, encaissé net, solde et statuts.'],
                ['format' => 'lignes', 'icon' => 'bi-list-ul', 'title' => 'Journal des lignes', 'description' => 'Une ligne par menu commandé : prix brut, remise, livraison et ventilation TVA.'],
                ['format' => 'mensuel', 'icon' => 'bi-calendar-month', 'title' => 'Récapitulatif mensuel', 'description' => 'Agrégats mensuels de CA commandes, volumes, panier moyen et TVA calculée.'],
            ];
            ?>
            <?php foreach ($exports as $export): ?>
                <div class="col-12 col-lg-4">
                    <div class="card h-100 shadow-sm comptabilite-export-card">
                        <div class="card-body d-flex flex-column">
                            <div class="comptabilite-export-icon mb-3"><i class="bi <?= sanitize($export['icon']) ?> text-brand" style="font-size:2rem"></i></div>
                            <h3 class="h6 fw-bold mb-1"><?= sanitize($export['title']) ?></h3>
                            <p class="small text-muted flex-grow-1"><?= sanitize($export['description']) ?></p>
                            <form method="GET" action="/admin/comptabilite/export" class="comptabilite-export-form mt-2">
                                <input type="hidden" name="format" value="<?= sanitize($export['format']) ?>">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Du</label>
                                        <input type="date" class="form-control form-control-sm" name="date_debut">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Au</label>
                                        <input type="date" class="form-control form-control-sm" name="date_fin" value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-brand btn-sm w-100"><i class="bi bi-download me-1"></i>Télécharger</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="mt-5">
            <ul class="small text-muted mb-0">
                <li>CSV séparateur <strong>;</strong>, encodage <strong>UTF-8 BOM</strong>.</li>
                <li>Les champs texte exportés sont neutralisés contre l'interprétation de formules par les tableurs.</li>
                <li>La <strong>date de comptabilisation</strong> correspond à la première acceptation connue, sinon à la date de commande.</li>
                <li>Les périodes d'export sont validées strictement avant toute requête.</li>
            </ul>
        </section>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'marges' ? 'show active' : '' ?>" id="pane-marges" role="tabpanel">
        <?php if (empty($coutsMatiere)): ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle me-2"></i>
                Aucune donnée de coût matière disponible. Renseignez des fiches techniques dans
                <a href="/employe/recettes">Fiches &amp; Stocks</a>.
            </div>
        <?php else: ?>
            <div class="alert alert-secondary">
                <strong>Pourquoi aucun taux de marge par plat ?</strong>
                Le prix vendu correspond au menu complet. Sans règle métier d'allocation du prix entre entrée, plat et dessert,
                attribuer tout le prix du menu à chaque plat produirait une marge fictive. Cette vue affiche donc uniquement le coût matière réellement calculable par portion.
            </div>

            <section class="stats-kpi-grid mb-4" aria-label="Indicateurs coûts matière">
                <article class="stats-kpi-card">
                    <span class="stats-kpi-label">Plats avec fiche</span>
                    <strong class="stats-kpi-value"><?= sanitize(formatInteger($nbCouts)) ?></strong>
                </article>
                <article class="stats-kpi-card">
                    <span class="stats-kpi-label">Coût moyen / portion</span>
                    <strong class="stats-kpi-value"><?= sanitize(formatPrice($coutMoyen)) ?></strong>
                    <span class="stats-kpi-note">Moyenne simple des plats</span>
                </article>
                <article class="stats-kpi-card">
                    <span class="stats-kpi-label">Coût max / portion</span>
                    <strong class="stats-kpi-value"><?= sanitize(formatPrice($coutMax)) ?></strong>
                </article>
                <article class="stats-kpi-card">
                    <span class="stats-kpi-label">Plats dans un menu actif</span>
                    <strong class="stats-kpi-value"><?= sanitize(formatInteger($nbUtilises)) ?></strong>
                </article>
            </section>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Plat</th>
                            <th>Catégorie</th>
                            <th class="text-end">Coût matière / portion</th>
                            <th class="text-end">Menus actifs</th>
                            <th>Utilisé dans</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coutsMatiere as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?= sanitize($row['titre']) ?></td>
                                <td class="text-muted small"><?= sanitize($row['categorie']) ?></td>
                                <td class="text-end fw-semibold"><?= sanitize(formatPrice($row['cout_matiere_portion'])) ?></td>
                                <td class="text-end"><?= sanitize(formatInteger($row['nb_menus_actifs'])) ?></td>
                                <td class="small">
                                    <?= $row['menus_actifs'] !== '' ? sanitize($row['menus_actifs']) : '<span class="text-muted">Aucun menu actif</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
(function () {
    document.querySelectorAll('#financesTabs [data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var pane = btn.getAttribute('data-bs-target').replace('#pane-', '');
            history.replaceState(null, '', '?tab=' + pane);
        });
    });
    var hash = window.location.hash.replace('#', '');
    if (hash) {
        var target = document.querySelector('[data-bs-target="#pane-' + hash + '"]');
        if (target) new bootstrap.Tab(target).show();
    }
}());
</script>

<?php if (!empty($caStats)): ?>
<?php partial('partials/chart_bar', [
    'chartId'      => 'chartCA',
    'chartLabels'  => $chartLabels,
    'chartData'    => $chartData,
    'datasetLabel' => 'Ventes menus TTC (hors livraison)',
    'valueFormat'  => 'currency',
    'horizontal'   => true,
]); ?>
<?php endif; ?>

<?php if (!empty($caMensuel) && !empty($mensuelAsc)): ?>
<?php partial('partials/chart_bar', [
    'chartId'      => 'chartMensuel',
    'chartLabels'  => $chartMensuelLabels,
    'chartData'    => $chartMensuelData,
    'datasetLabel' => 'CA commandes TTC mensuel',
    'valueFormat'  => 'currency',
    'horizontal'   => false,
]); ?>
<?php endif; ?>
