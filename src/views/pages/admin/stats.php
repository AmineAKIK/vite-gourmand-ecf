<?php
// src/views/pages/admin/stats.php
$pageTitle = buildPageTitle('Statistiques CA');

$isAssujetti  = ($regimeTva ?? 'assujetti') === 'assujetti';

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

// Tendance mensuelle — du plus ancien au plus récent pour le graphique
$mensuelAsc         = array_reverse($caMensuel ?? []);
$chartMensuelLabels = array_map(fn($r) => $r['annee_mois'] ?? '', $mensuelAsc);
$chartMensuelData   = array_map(fn($r) => round((float)($r['ca_ttc'] ?? 0), 2), $mensuelAsc);

$exportUrl = '/admin/stats/export?' . http_build_query(array_filter([
    'date_debut' => $dateDebut ?? '',
    'date_fin'   => $dateFin   ?? '',
]));
?>

<div class="container py-4 stats-page">

    <div class="stats-heading mb-4">
        <div>
            <?php partial('partials/page_title_bar', ['icon' => 'bi-graph-up', 'title' => "Statistiques CA"]); ?>
            <p class="stats-heading-subtitle mb-0">
                Vue synthétique du chiffre d'affaires. Source : commandes avec statut accepté ou ultérieur.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="stats-period-badge">
                <i class="bi bi-calendar3 me-1"></i><?= sanitize($periodLabel) ?>
            </span>
            <a href="<?= sanitize($exportUrl) ?>" class="btn btn-outline-secondary btn-sm">
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
    <section class="stats-filter-panel mb-4" aria-label="Filtres des statistiques">
        <form method="GET" action="/admin/stats" class="row g-3 align-items-end" role="search">
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
                <a href="/admin/stats" class="btn btn-outline-secondary btn-sm btn-reset-filters <?= $activeFilters ? '' : 'disabled' ?>">
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
                    <div>
                        <h2>CA par menu (TTC)</h2>
                        <p>Classement sur la période filtrée.</p>
                    </div>
                </div>
                <div class="stats-chart-wrap">
                    <canvas id="chartCA" aria-label="Graphique CA par menu" role="img"></canvas>
                </div>
            </section>
        </div>
        <?php endif; ?>

        <?php if (!empty($caMensuel)): ?>
        <div class="col-12 <?= !empty($caStats) ? 'col-xl-6' : '' ?>">
            <section class="stats-panel h-100">
                <div class="stats-panel-header">
                    <div>
                        <h2>Tendance mensuelle (TTC)</h2>
                        <p><?= count($caMensuel) ?> mois · panier moyen inclus.</p>
                    </div>
                </div>
                <div class="stats-chart-wrap">
                    <canvas id="chartMensuel" aria-label="Graphique tendance mensuelle" role="img"></canvas>
                </div>
            </section>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tableau CA par menu -->
    <?php if (!empty($caStats)): ?>
    <section class="stats-panel stats-detail-panel mb-4">
        <div class="stats-panel-header stats-panel-header--table">
            <div>
                <h2>Détail par menu</h2>
                <p>CA<?= $isAssujetti ? ', HT et TVA' : '' ?>, volume et part dans le total.</p>
            </div>
        </div>
        <div class="table-responsive stats-table-desktop">
            <table class="table stats-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Menu</th>
                        <th scope="col" class="text-end text-nowrap">Commandes</th>
                        <th scope="col" class="text-end text-nowrap">Panier moyen</th>
                        <?php if ($isAssujetti): ?>
                        <th scope="col" class="text-end text-nowrap">CA HT</th>
                        <th scope="col" class="text-end text-nowrap">TVA</th>
                        <?php endif; ?>
                        <th scope="col" class="text-end text-nowrap">Part CA</th>
                        <th scope="col" class="text-end text-nowrap">CA TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($caStats as $row):
                        $nb      = (int)  ($row['nb']     ?? 0);
                        $ca      = (float)($row['ca']     ?? 0);
                        $caHT    = (float)($row['ca_ht']  ?? 0);
                        $tva     = $ca - $caHT;
                        $average = $nb > 0 ? $ca / $nb : 0;
                        $share   = $totalTTC > 0 ? ($ca / $totalTTC) * 100 : 0;
                    ?>
                    <tr>
                        <td class="stats-table-title" data-label="Menu"><?= sanitize($row['titre'] ?? '') ?></td>
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

        <!-- Mobile cards -->
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
            <?php if (count($caStats) > 1): ?>
                <article class="stats-menu-card stats-menu-card-total">
                    <div class="stats-menu-card-head">
                        <strong>Total</strong>
                        <span><?= sanitize(formatPrice($totalTTC)) ?></span>
                    </div>
                    <dl>
                        <div><dt>Commandes</dt><dd><?= sanitize(formatInteger($totalNb)) ?></dd></div>
                        <div><dt>Panier moyen</dt><dd><?= sanitize(formatPrice($panierMoyen)) ?></dd></div>
                        <?php if ($isAssujetti): ?>
                        <div><dt>HT</dt><dd><?= sanitize(formatPrice($totalHT)) ?></dd></div>
                        <div><dt>TVA</dt><dd><?= sanitize(formatPrice($totalTVA)) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Part CA</dt><dd>100 %</dd></div>
                    </dl>
                </article>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Tableau tendance mensuelle -->
    <?php if (!empty($caMensuel)): ?>
    <section class="stats-panel stats-detail-panel">
        <div class="stats-panel-header stats-panel-header--table">
            <div>
                <h2>Tendance mensuelle</h2>
                <p>CA par mois de comptabilisation (date d'acceptation).</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table stats-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Mois</th>
                        <th scope="col" class="text-end text-nowrap">Commandes</th>
                        <th scope="col" class="text-end text-nowrap">Personnes</th>
                        <th scope="col" class="text-end text-nowrap">Panier moyen</th>
                        <?php if ($isAssujetti): ?>
                        <th scope="col" class="text-end text-nowrap">CA HT</th>
                        <th scope="col" class="text-end text-nowrap">TVA collectée</th>
                        <?php endif; ?>
                        <th scope="col" class="text-end text-nowrap">CA TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($caMensuel as $mois): ?>
                    <tr>
                        <td data-label="Mois"><?= sanitize($mois['annee_mois'] ?? '') ?></td>
                        <td class="text-end" data-label="Commandes"><?= sanitize(formatInteger($mois['nb_commandes'] ?? 0)) ?></td>
                        <td class="text-end" data-label="Personnes"><?= sanitize(formatInteger($mois['nb_personnes'] ?? 0)) ?></td>
                        <td class="text-end text-nowrap" data-label="Panier moyen"><?= sanitize(formatPrice($mois['panier_moyen_ttc'] ?? 0)) ?></td>
                        <?php if ($isAssujetti): ?>
                        <td class="text-end text-nowrap" data-label="CA HT"><?= sanitize(formatPrice($mois['ca_ht'] ?? 0)) ?></td>
                        <td class="text-end text-nowrap text-muted" data-label="TVA"><?= sanitize(formatPrice($mois['tva_collectee'] ?? 0)) ?></td>
                        <?php endif; ?>
                        <td class="text-end fw-bold text-vg text-nowrap" data-label="CA TTC"><?= sanitize(formatPrice($mois['ca_ttc'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php endif; ?>
</div>

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
