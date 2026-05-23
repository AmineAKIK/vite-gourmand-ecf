<?php
// src/views/pages/admin/stats.php
$pageTitle = 'Statistiques CA - Vite & Gourmand';

$totalCA = array_sum(array_map(fn($row) => (float)($row['ca'] ?? 0), $caStats ?? []));
$totalNb = array_sum(array_map(fn($row) => (int)($row['nb'] ?? 0), $caStats ?? []));
$panierMoyen = $totalNb > 0 ? $totalCA / $totalNb : 0;
$topMenu = $caStats[0] ?? null;
$topMenuShare = ($topMenu && $totalCA > 0) ? ((float)$topMenu['ca'] / $totalCA) * 100 : 0;
$activeFilters = (int)($menuFilter ?? 0) > 0 || !empty($dateDebut) || !empty($dateFin);

$periodLabel = 'Toutes les commandes comptabilisées';
if (!empty($dateDebut) && !empty($dateFin)) {
    $periodLabel = 'Du ' . formatDateFr($dateDebut) . ' au ' . formatDateFr($dateFin);
} elseif (!empty($dateDebut)) {
    $periodLabel = 'Depuis le ' . formatDateFr($dateDebut);
} elseif (!empty($dateFin)) {
    $periodLabel = "Jusqu'au " . formatDateFr($dateFin);
}

$chartLabels = array_map(fn($row) => $row['titre'] ?? '', $caStats ?? []);
$chartData = array_map(fn($row) => round((float)($row['ca'] ?? 0), 2), $caStats ?? []);
?>

<div class="container py-4 stats-page">

    <div class="stats-heading mb-4">
        <div>
            <?php partial('partials/page_title_bar', ['icon' => 'bi-graph-up', 'title' => "Statistiques CA"]); ?>
            <p class="stats-heading-subtitle mb-0">
                Vue synthétique du chiffre d'affaires par menu et de la performance commerciale.
            </p>
        </div>
        <div class="stats-period-badge">
            <i class="bi bi-calendar3 me-1"></i><?= sanitize($periodLabel) ?>
        </div>
    </div>

    <section class="stats-kpi-grid mb-4" aria-label="Indicateurs de synthèse">
        <article class="stats-kpi-card">
            <span class="stats-kpi-label">Chiffre d'affaires</span>
            <strong class="stats-kpi-value"><?= sanitize(formatPrice($totalCA)) ?></strong>
            <span class="stats-kpi-note">Hors commandes annulées</span>
        </article>
        <article class="stats-kpi-card">
            <span class="stats-kpi-label">Commandes</span>
            <strong class="stats-kpi-value"><?= sanitize(formatInteger($totalNb)) ?></strong>
            <span class="stats-kpi-note">Sur la période affichée</span>
        </article>
        <article class="stats-kpi-card">
            <span class="stats-kpi-label">Panier moyen</span>
            <strong class="stats-kpi-value"><?= sanitize(formatPrice($panierMoyen)) ?></strong>
            <span class="stats-kpi-note">CA divisé par commandes</span>
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

    <section class="stats-filter-panel mb-4" aria-label="Filtres des statistiques">
        <form method="GET" action="/admin/stats" class="row g-3 align-items-end" role="search">
            <div class="col-12 col-xl-4">
                <label for="filtre-menu" class="form-label form-label-sm">Menu</label>
                <select class="form-select form-select-sm" id="filtre-menu" name="menu_id" aria-label="Filtrer par menu">
                    <option value="">Tous les menus</option>
                    <?php foreach ($menus as $m): ?>
                        <option value="<?= (int)$m['menu_id'] ?>" <?= (int)($menuFilter ?? 0) === (int)$m['menu_id'] ? 'selected' : '' ?>>
                            <?= sanitize($m['titre'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-6">
                <label for="filtre-debut" class="form-label form-label-sm">Date début</label>
                <input
                    type="date"
                    class="form-control form-control-sm"
                    id="filtre-debut"
                    name="date_debut"
                    value="<?= sanitize($dateDebut ?? '') ?>"
                    aria-label="Date de début de la période"
                >
            </div>
            <div class="col-12 col-lg-6">
                <label for="filtre-fin" class="form-label form-label-sm">Date fin</label>
                <input
                    type="date"
                    class="form-control form-control-sm"
                    id="filtre-fin"
                    name="date_fin"
                    value="<?= sanitize($dateFin ?? '') ?>"
                    aria-label="Date de fin de la période"
                >
            </div>
            <div class="col-12 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-vg btn-sm flex-grow-1" aria-label="Filtrer les statistiques">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
                <a href="/admin/stats" class="btn btn-outline-secondary btn-sm btn-reset-filters <?= $activeFilters ? '' : 'disabled' ?>" <?= $activeFilters ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Réinitialiser
                </a>
            </div>
        </form>
    </section>

    <?php if (empty($caStats)): ?>
        <div class="stats-empty-state">
            <i class="bi bi-bar-chart"></i>
            <strong>Aucune donnée pour cette sélection</strong>
            <span>Essayez une période plus large ou retirez le filtre menu.</span>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-8">
                <section class="stats-panel h-100">
                    <div class="stats-panel-header">
                        <div>
                            <h2>CA par menu</h2>
                            <p>Classement par chiffre d'affaires généré.</p>
                        </div>
                    </div>
                    <div class="stats-chart-wrap">
                        <canvas id="chartCA" aria-label="Graphique du chiffre d'affaires par menu" role="img"></canvas>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="stats-panel h-100">
                    <div class="stats-panel-header">
                        <div>
                            <h2>Lecture rapide</h2>
                            <p>Menus qui tirent le plus l'activité.</p>
                        </div>
                    </div>
                    <div class="stats-ranking">
                        <?php foreach (array_slice($caStats, 0, 3) as $index => $row):
                            $ca = (float)($row['ca'] ?? 0);
                            $share = $totalCA > 0 ? ($ca / $totalCA) * 100 : 0;
                        ?>
                            <article class="stats-ranking-item">
                                <div class="stats-ranking-main">
                                    <span class="stats-rank"><?= $index + 1 ?></span>
                                    <div>
                                        <strong><?= sanitize($row['titre'] ?? '') ?></strong>
                                        <span><?= sanitize(formatInteger($row['nb'] ?? 0)) ?> commande<?= (int)($row['nb'] ?? 0) > 1 ? 's' : '' ?></span>
                                    </div>
                                </div>
                                <div class="stats-ranking-value">
                                    <strong><?= sanitize(formatPrice($ca)) ?></strong>
                                    <span><?= sanitize(number_format($share, 0, ',', ' ')) ?> %</span>
                                </div>
                                <div class="stats-share-track" aria-hidden="true">
                                    <span style="width:<?= min(100, max(0, $share)) ?>%"></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>

        <section class="stats-panel">
            <div class="stats-panel-header stats-panel-header--table">
                <div>
                    <h2>Détail par menu</h2>
                    <p>CA, volume, panier moyen et part dans le total.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table stats-table align-middle mb-0" aria-label="Chiffre d'affaires détaillé par menu">
                    <thead>
                        <tr>
                            <th scope="col">Menu</th>
                            <th scope="col" class="text-end text-nowrap">Commandes</th>
                            <th scope="col" class="text-end text-nowrap">Panier moyen</th>
                            <th scope="col" class="text-end text-nowrap">Part CA</th>
                            <th scope="col" class="text-end text-nowrap">CA total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caStats as $row):
                            $nb = (int)($row['nb'] ?? 0);
                            $ca = (float)($row['ca'] ?? 0);
                            $average = $nb > 0 ? $ca / $nb : 0;
                            $share = $totalCA > 0 ? ($ca / $totalCA) * 100 : 0;
                        ?>
                        <tr>
                            <td class="stats-table-title"><?= sanitize($row['titre'] ?? '') ?></td>
                            <td class="text-end"><?= sanitize(formatInteger($nb)) ?></td>
                            <td class="text-end text-nowrap"><?= sanitize(formatPrice($average)) ?></td>
                            <td class="text-end">
                                <span class="stats-percent"><?= sanitize(number_format($share, 0, ',', ' ')) ?> %</span>
                            </td>
                            <td class="text-end fw-bold text-vg text-nowrap"><?= sanitize(formatPrice($ca)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="text-end"><?= sanitize(formatInteger($totalNb)) ?></td>
                            <td class="text-end text-nowrap"><?= sanitize(formatPrice($panierMoyen)) ?></td>
                            <td class="text-end">100 %</td>
                            <td class="text-end text-vg text-nowrap"><?= sanitize(formatPrice($totalCA)) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php if (!empty($caStats)): ?>
<?php partial('partials/chart_bar', [
    'chartId' => 'chartCA',
    'chartLabels' => $chartLabels,
    'chartData' => $chartData,
    'datasetLabel' => "Chiffre d'affaires",
    'valueFormat' => 'currency',
    'horizontal' => true,
]); ?>
<?php endif; ?>
