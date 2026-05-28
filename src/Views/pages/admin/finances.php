<?php
$pageTitle   = buildPageTitle('Finances');
$cspNonce    = $GLOBALS['csp_nonce'] ?? '';
$isAssujetti = ($regimeTva ?? 'assujetti') === 'assujetti';

// --- Stats ---
$totalTTC     = (float)($synthese['total_ttc']        ?? 0);
$totalHT      = (float)($synthese['total_ht']         ?? 0);
$totalTVA     = (float)($synthese['total_tva']        ?? 0);
$totalNb      = (int)  ($synthese['nb_commandes']     ?? 0);
$encaisse     = (float)($synthese['montant_encaisse'] ?? 0);
$soldeRestant = (float)($synthese['solde_restant']    ?? 0);
$panierMoyen  = $totalNb > 0 ? $totalTTC / $totalNb : 0;
$topMenu      = $caStats[0] ?? null;
$topMenuShare = ($topMenu && $totalTTC > 0) ? ((float)$topMenu['ca'] / $totalTTC) * 100 : 0;
$activeFilters = (int)($menuFilter ?? 0) > 0 || !empty($dateDebut) || !empty($dateFin);

$periodLabel = 'Toutes les commandes acceptées';
if (!empty($dateDebut) && !empty($dateFin)) {
    $periodLabel = 'Du ' . formatDateFr($dateDebut) . ' au ' . formatDateFr($dateFin);
} elseif (!empty($dateDebut)) {
    $periodLabel = 'Depuis le ' . formatDateFr($dateDebut);
} elseif (!empty($dateFin)) {
    $periodLabel = "Jusqu'au " . formatDateFr($dateFin);
}

$chartLabels = array_map(fn($r) => $r['titre'] ?? '', $caStats ?? []);
$chartData   = array_map(fn($r) => round((float)($r['ca'] ?? 0), 2), $caStats ?? []);
$mensuelAsc         = array_reverse($caMensuel ?? []);
$chartMensuelLabels = array_map(fn($r) => $r['annee_mois'] ?? '', $mensuelAsc);
$chartMensuelData   = array_map(fn($r) => round((float)($r['ca_ttc'] ?? 0), 2), $mensuelAsc);

$exportStatsUrl = '/admin/stats/export?' . http_build_query(array_filter([
    'date_debut' => $dateDebut ?? '',
    'date_fin'   => $dateFin   ?? '',
]));

// --- Comptabilité ---
$siret = trim((string)($config['entreprise_siret'] ?? ''));

$activeTab = $_GET['tab'] ?? 'stats';
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
</ul>

<div class="tab-content">

    <!-- ============================================================
         ONGLET 1 — Statistiques CA
    ============================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'stats' ? 'show active' : '' ?>" id="pane-stats" role="tabpanel">

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <p class="text-muted mb-0">Vue synthétique du chiffre d'affaires. Source : commandes au statut accepté ou ultérieur.</p>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="stats-period-badge">
                    <i class="bi bi-calendar3 me-1"></i><?= sanitize($periodLabel) ?>
                </span>
                <a href="<?= sanitize($exportStatsUrl) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
        </div>

        <!-- KPI -->
        <section class="stats-kpi-grid mb-4" aria-label="Indicateurs de synthèse">
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">CA TTC</span>
                <strong class="stats-kpi-value"><?= sanitize(formatPrice($totalTTC)) ?></strong>
                <?php if ($isAssujetti): ?>
                <span class="stats-kpi-note">HT : <?= sanitize(formatPrice($totalHT)) ?> · TVA : <?= sanitize(formatPrice($totalTVA)) ?></span>
                <?php else: ?>
                <span class="stats-kpi-note">Régime non-assujetti (art. 293 B)</span>
                <?php endif; ?>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Commandes</span>
                <strong class="stats-kpi-value"><?= sanitize(formatInteger($totalNb)) ?></strong>
                <span class="stats-kpi-note">Panier moyen <?= sanitize(formatPrice($panierMoyen)) ?></span>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Encaissé</span>
                <strong class="stats-kpi-value"><?= sanitize(formatPrice($encaisse)) ?></strong>
                <?php if ($soldeRestant > 0): ?>
                <span class="stats-kpi-note stats-kpi-note--alert">Solde restant : <?= sanitize(formatPrice($soldeRestant)) ?></span>
                <?php else: ?>
                <span class="stats-kpi-note">Tout soldé</span>
                <?php endif; ?>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Meilleur menu</span>
                <strong class="stats-kpi-value stats-kpi-value--text">
                    <?= sanitize($topMenu['titre'] ?? 'Aucun') ?>
                </strong>
                <span class="stats-kpi-note">
                    <?= $topMenu ? sanitize(number_format($topMenuShare, 0, ',', ' ') . ' % du CA') : 'Aucune donnée' ?>
                </span>
            </article>
        </section>

        <!-- Filtres -->
        <section class="stats-filter-panel mb-4" aria-label="Filtres">
            <form method="GET" action="/admin/stats" class="row g-3 align-items-end" role="search">
                <input type="hidden" name="tab" value="stats">
                <div class="col-12 col-xl-4">
                    <label for="filtre-menu" class="form-label form-label-sm">Menu</label>
                    <select class="form-select form-select-sm" id="filtre-menu" name="menu_id">
                        <option value="">Tous les menus</option>
                        <?php foreach ($menus as $m): ?>
                            <option value="<?= (int)$m['menu_id'] ?>" <?= (int)($menuFilter ?? 0) === (int)$m['menu_id'] ? 'selected' : '' ?>>
                                <?= sanitize($m['titre'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label for="filtre-debut" class="form-label form-label-sm">Date début</label>
                    <input type="date" class="form-control form-control-sm" id="filtre-debut" name="date_debut"
                           value="<?= sanitize($dateDebut ?? '') ?>">
                </div>
                <div class="col-12 col-lg-3">
                    <label for="filtre-fin" class="form-label form-label-sm">Date fin</label>
                    <input type="date" class="form-control form-control-sm" id="filtre-fin" name="date_fin"
                           value="<?= sanitize($dateFin ?? '') ?>">
                </div>
                <div class="col-12 col-xl-2 d-flex gap-2">
                    <button type="submit" class="btn btn-vg btn-sm flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Filtrer
                    </button>
                    <a href="/admin/stats" class="btn btn-outline-secondary btn-sm <?= $activeFilters ? '' : 'disabled' ?>">
                        Réinitialiser
                    </a>
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

        <!-- Graphiques -->
        <div class="row g-4 mb-4">
            <?php if (!empty($caStats)): ?>
            <div class="col-12 <?= !empty($caMensuel) ? 'col-xl-6' : 'col-xl-8 offset-xl-2' ?>">
                <section class="stats-panel h-100">
                    <div class="stats-panel-header">
                        <div><h2>CA par menu (TTC)</h2><p>Classement sur la période filtrée.</p></div>
                    </div>
                    <div class="stats-chart-wrap">
                        <canvas id="chartCA" aria-label="CA par menu" role="img"></canvas>
                    </div>
                </section>
            </div>
            <?php endif; ?>
            <?php if (!empty($caMensuel)): ?>
            <div class="col-12 <?= !empty($caStats) ? 'col-xl-6' : '' ?>">
                <section class="stats-panel h-100">
                    <div class="stats-panel-header">
                        <div><h2>Tendance mensuelle (TTC)</h2><p><?= count($caMensuel) ?> mois.</p></div>
                    </div>
                    <div class="stats-chart-wrap">
                        <canvas id="chartMensuel" aria-label="Tendance mensuelle" role="img"></canvas>
                    </div>
                </section>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tableau CA par menu -->
        <?php if (!empty($caStats)): ?>
        <section class="stats-panel stats-detail-panel mb-4">
            <div class="stats-panel-header stats-panel-header--table">
                <div><h2>Détail par menu</h2><p>CA<?= $isAssujetti ? ', HT et TVA' : '' ?>, volume et part.</p></div>
            </div>
            <div class="table-responsive stats-table-desktop">
                <table class="table stats-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th class="text-end text-nowrap">Commandes</th>
                            <th class="text-end text-nowrap">Panier moyen</th>
                            <?php if ($isAssujetti): ?>
                            <th class="text-end text-nowrap">CA HT</th>
                            <th class="text-end text-nowrap">TVA</th>
                            <?php endif; ?>
                            <th class="text-end text-nowrap">Part CA</th>
                            <th class="text-end text-nowrap">CA TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caStats as $row):
                            $nb      = (int)  ($row['nb']    ?? 0);
                            $ca      = (float)($row['ca']    ?? 0);
                            $caHT    = (float)($row['ca_ht'] ?? 0);
                            $tva     = $ca - $caHT;
                            $average = $nb > 0 ? $ca / $nb : 0;
                            $share   = $totalTTC > 0 ? ($ca / $totalTTC) * 100 : 0;
                        ?>
                        <tr>
                            <td data-label="Menu"><?= sanitize($row['titre'] ?? '') ?></td>
                            <td class="text-end" data-label="Commandes"><?= sanitize(formatInteger($nb)) ?></td>
                            <td class="text-end text-nowrap" data-label="Panier moyen"><?= sanitize(formatPrice($average)) ?></td>
                            <?php if ($isAssujetti): ?>
                            <td class="text-end text-nowrap" data-label="CA HT"><?= sanitize(formatPrice($caHT)) ?></td>
                            <td class="text-end text-nowrap text-muted" data-label="TVA"><?= sanitize(formatPrice($tva)) ?></td>
                            <?php endif; ?>
                            <td class="text-end" data-label="Part CA">
                                <span class="stats-percent"><?= sanitize(number_format($share, 0, ',', ' ')) ?> %</span>
                            </td>
                            <td class="text-end fw-bold text-vg text-nowrap" data-label="CA TTC"><?= sanitize(formatPrice($ca)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="text-end"><?= sanitize(formatInteger($totalNb)) ?></td>
                            <td class="text-end text-nowrap"><?= sanitize(formatPrice($panierMoyen)) ?></td>
                            <?php if ($isAssujetti): ?>
                            <td class="text-end text-nowrap"><?= sanitize(formatPrice($totalHT)) ?></td>
                            <td class="text-end text-nowrap text-muted"><?= sanitize(formatPrice($totalTVA)) ?></td>
                            <?php endif; ?>
                            <td class="text-end">100 %</td>
                            <td class="text-end text-vg text-nowrap"><?= sanitize(formatPrice($totalTTC)) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="stats-menu-cards">
                <?php foreach ($caStats as $row):
                    $nb    = (int)  ($row['nb']    ?? 0);
                    $ca    = (float)($row['ca']    ?? 0);
                    $caHT  = (float)($row['ca_ht'] ?? 0);
                    $tva   = $ca - $caHT;
                    $avg   = $nb > 0 ? $ca / $nb : 0;
                    $share = $totalTTC > 0 ? ($ca / $totalTTC) * 100 : 0;
                ?>
                    <article class="stats-menu-card stats-menu-card-row">
                        <div class="stats-menu-card-head">
                            <strong><?= sanitize($row['titre'] ?? '') ?></strong>
                            <span><?= sanitize(formatPrice($ca)) ?></span>
                        </div>
                        <dl>
                            <div><dt>Commandes</dt><dd><?= sanitize(formatInteger($nb)) ?></dd></div>
                            <div><dt>Panier moyen</dt><dd><?= sanitize(formatPrice($avg)) ?></dd></div>
                            <?php if ($isAssujetti): ?>
                            <div><dt>HT</dt><dd><?= sanitize(formatPrice($caHT)) ?></dd></div>
                            <div><dt>TVA</dt><dd><?= sanitize(formatPrice($tva)) ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Part CA</dt><dd><?= sanitize(number_format($share, 0, ',', ' ')) ?> %</dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Tendance mensuelle -->
        <?php if (!empty($caMensuel)): ?>
        <section class="stats-panel stats-detail-panel">
            <div class="stats-panel-header stats-panel-header--table">
                <div><h2>Tendance mensuelle</h2><p>CA par mois de comptabilisation.</p></div>
            </div>
            <div class="table-responsive">
                <table class="table stats-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th class="text-end text-nowrap">Commandes</th>
                            <th class="text-end text-nowrap">Personnes</th>
                            <th class="text-end text-nowrap">Panier moyen</th>
                            <?php if ($isAssujetti): ?>
                            <th class="text-end text-nowrap">CA HT</th>
                            <th class="text-end text-nowrap">TVA</th>
                            <?php endif; ?>
                            <th class="text-end text-nowrap">CA TTC</th>
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
                            <td class="text-end fw-bold text-vg text-nowrap"><?= sanitize(formatPrice($mois['ca_ttc'] ?? 0)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php endif; // fin empty check ?>
    </div>

    <!-- ============================================================
         ONGLET 2 — Comptabilité
    ============================================================ -->
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
                Sans SIRET, les documents générés ne sont pas fiscalement valides.
                <a href="/admin/parametres?tab=entreprise" class="alert-link ms-1">Configurer l'entreprise →</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- KPI snapshot -->
        <section class="stats-kpi-grid mb-5" aria-label="Soldes globaux">
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">CA TTC cumulé</span>
                <strong class="stats-kpi-value"><?= sanitize(formatPrice($totalTTC)) ?></strong>
                <?php if ($isAssujetti): ?>
                <span class="stats-kpi-note">HT : <?= sanitize(formatPrice($totalHT)) ?></span>
                <?php endif; ?>
            </article>
            <?php if ($isAssujetti): ?>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">TVA collectée</span>
                <strong class="stats-kpi-value"><?= sanitize(formatPrice($totalTVA)) ?></strong>
                <span class="stats-kpi-note">Toutes périodes</span>
            </article>
            <?php endif; ?>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Encaissé</span>
                <strong class="stats-kpi-value"><?= sanitize(formatPrice($encaisse)) ?></strong>
                <?php if ($soldeRestant > 0): ?>
                <span class="stats-kpi-note stats-kpi-note--alert">Solde restant : <?= sanitize(formatPrice($soldeRestant)) ?></span>
                <?php else: ?>
                <span class="stats-kpi-note">Tout soldé</span>
                <?php endif; ?>
            </article>
            <article class="stats-kpi-card">
                <span class="stats-kpi-label">Commandes</span>
                <strong class="stats-kpi-value"><?= sanitize(formatInteger($totalNb)) ?></strong>
                <span class="stats-kpi-note">Acceptées et au-delà</span>
            </article>
        </section>

        <!-- Exports -->
        <h2 class="h5 mb-4 fw-semibold">Exports CSV</h2>
        <div class="row g-4">

            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm comptabilite-export-card">
                    <div class="card-body d-flex flex-column">
                        <div class="comptabilite-export-icon mb-3">
                            <i class="bi bi-file-earmark-spreadsheet text-vg" style="font-size:2rem"></i>
                        </div>
                        <h3 class="h6 fw-bold mb-1">Journal des commandes</h3>
                        <p class="small text-muted flex-grow-1">
                            Une ligne par commande. Contient : numéro, dates, client, total TTC
                            <?= $isAssujetti ? ', HT, TVA' : '' ?>, encaissé, solde, statut paiement.
                        </p>
                        <form method="GET" action="/admin/comptabilite/export" class="comptabilite-export-form mt-2">
                            <input type="hidden" name="format" value="commandes">
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
                            <button type="submit" class="btn btn-vg btn-sm w-100">
                                <i class="bi bi-download me-1"></i>Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm comptabilite-export-card">
                    <div class="card-body d-flex flex-column">
                        <div class="comptabilite-export-icon mb-3">
                            <i class="bi bi-list-ul text-vg" style="font-size:2rem"></i>
                        </div>
                        <h3 class="h6 fw-bold mb-1">Journal des lignes</h3>
                        <p class="small text-muted flex-grow-1">
                            Une ligne par menu dans chaque commande. Prix brut, remise, livraison<?= $isAssujetti ? ', HT et TVA par ligne' : '' ?>.
                        </p>
                        <form method="GET" action="/admin/comptabilite/export" class="comptabilite-export-form mt-2">
                            <input type="hidden" name="format" value="lignes">
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
                            <button type="submit" class="btn btn-vg btn-sm w-100">
                                <i class="bi bi-download me-1"></i>Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm comptabilite-export-card">
                    <div class="card-body d-flex flex-column">
                        <div class="comptabilite-export-icon mb-3">
                            <i class="bi bi-calendar-month text-vg" style="font-size:2rem"></i>
                        </div>
                        <h3 class="h6 fw-bold mb-1">Récapitulatif mensuel</h3>
                        <p class="small text-muted flex-grow-1">
                            Agrégé par mois : CA TTC<?= $isAssujetti ? ', HT, TVA collectée' : '' ?>, panier moyen.
                            Pour les déclarations<?= $isAssujetti ? ' TVA (CA3, CA12)' : '' ?>.
                        </p>
                        <form method="GET" action="/admin/comptabilite/export" class="comptabilite-export-form mt-2">
                            <input type="hidden" name="format" value="mensuel">
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
                            <button type="submit" class="btn btn-vg btn-sm w-100">
                                <i class="bi bi-download me-1"></i>Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <section class="mt-5">
            <ul class="small text-muted mb-0">
                <li>CSV séparateur <strong>;</strong>, encodage <strong>UTF-8 BOM</strong> — compatibles Excel et LibreOffice.</li>
                <li>La <strong>date de comptabilisation</strong> = date d'acceptation de la commande.</li>
                <?php if ($isAssujetti): ?>
                <li>Pour votre déclaration TVA, utilisez le <strong>récapitulatif mensuel</strong>.</li>
                <?php else: ?>
                <li>Régime non-assujetti : aucune TVA à reverser. Vos factures ne doivent pas mentionner de TVA.</li>
                <?php endif; ?>
            </ul>
        </section>

    </div>

</div><!-- /.tab-content -->

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
    'datasetLabel' => "CA TTC",
    'valueFormat'  => 'currency',
    'horizontal'   => true,
]); ?>
<?php endif; ?>

<?php if (!empty($caMensuel) && !empty($mensuelAsc)): ?>
<?php partial('partials/chart_bar', [
    'chartId'      => 'chartMensuel',
    'chartLabels'  => $chartMensuelLabels,
    'chartData'    => $chartMensuelData,
    'datasetLabel' => "CA TTC mensuel",
    'valueFormat'  => 'currency',
    'horizontal'   => false,
]); ?>
<?php endif; ?>
