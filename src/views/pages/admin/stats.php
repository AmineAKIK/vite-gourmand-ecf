<?php
// src/views/pages/admin/stats.php
$pageTitle = 'Statistiques CA - Vite & Gourmand';

/* Calcule le total général */
$totalCA = array_sum(array_column($caStats ?? [], 'ca'));
$totalNb = array_sum(array_column($caStats ?? [], 'nb'));

/* Prépare les données Chart.js (barres horizontales) */
$chartLabels = array_map(fn($s) => $s['titre'] ?? '', $mongoStats ?? []);
$chartData   = array_column($mongoStats ?? [], 'nb_commandes');
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-graph-up', 'title' => "Statistiques — Chiffre d'affaires"]); ?>

    <!-- Formulaire de filtres -->
    <div class="filtres-panel card shadow-sm p-3 mb-4" style="border:1px solid rgba(0,0,0,.08);">
        <form method="GET" action="/admin/stats" class="row g-2 align-items-end" role="search" aria-label="Filtrer les statistiques">
            <div class="col-md-4">
                <label for="filtre-menu" class="form-label form-label-sm">Menu (optionnel)</label>
                <select class="form-select form-select-sm" id="filtre-menu" name="menu_id" aria-label="Filtrer par menu">
                    <option value="">— Tous les menus —</option>
                    <?php foreach ($menus as $m): ?>
                        <option value="<?= (int)$m['menu_id'] ?>" <?= (int)($menuFilter ?? 0) === (int)$m['menu_id'] ? 'selected' : '' ?>>
                            <?= sanitize($m['titre'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-4 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-vg btn-sm flex-grow-1" aria-label="Filtrer les statistiques">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
                <a href="/admin/stats" class="btn btn-outline-secondary btn-sm btn-reset-filters">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <div class="row g-4">

        <!-- Tableau CA par menu -->
        <div class="col-lg-6">
            <?php if (empty($caStats)): ?>
                <div class="alert alert-info">Aucune donnée pour cette période.</div>
            <?php else: ?>
                <div class="card shadow-sm" style="border:1px solid rgba(0,0,0,.08);">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" aria-label="Chiffre d'affaires par menu">
                            <thead>
                                <tr style="background:rgba(0,0,0,.03); border-bottom:1px solid rgba(0,0,0,.08);">
                                    <th scope="col" class="ps-3 text-vg fw-semibold">Menu</th>
                                    <th scope="col" class="text-end text-vg fw-semibold">Nb commandes</th>
                                    <th scope="col" class="text-end text-vg fw-semibold pe-3">CA total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($caStats as $row): ?>
                                <tr>
                                    <td class="ps-3"><?= sanitize($row['titre']) ?></td>
                                    <td class="text-end text-muted"><?= sanitize(formatInteger($row['nb'] ?? 0)) ?></td>
                                    <td class="text-end fw-semibold text-vg pe-3">
                                        <?= sanitize(formatPrice($row['ca'] ?? 0)) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background:rgba(0,0,0,.03); border-top:1px solid rgba(0,0,0,.08);">
                                <tr>
                                    <td class="fw-bold ps-3">TOTAL</td>
                                    <td class="text-end fw-bold"><?= $totalNb ?></td>
                                    <td class="text-end fw-bold text-vg pe-3"><?= sanitize(formatPrice($totalCA)) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Graphique barres horizontales -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100" style="border:1px solid rgba(0,0,0,.08);">
                <div class="card-header fw-semibold" style="background:rgba(0,0,0,.03); border-bottom:1px solid rgba(0,0,0,.08);">
                    <i class="bi bi-bar-chart-horizontal me-2 text-vg"></i>Commandes par menu (MongoDB)
                </div>
                <div class="card-body">
                    <?php if (empty($mongoStats)): ?>
                        <p class="text-muted small">Aucune donnée à afficher.</p>
                    <?php else: ?>
                        <div style="position:relative;height:260px">
                            <canvas id="chartCA" aria-label="Graphique du chiffre d'affaires par menu" role="img"></canvas>
                        </div>
                        <p class="text-muted small text-center mt-2 mb-0">
                            <i class="bi bi-database me-1"></i>Données issues de MongoDB
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->

</div>

<?php if (!empty($mongoStats)): ?>
<?php partial('partials/chart_bar', [
    'chartId' => 'chartCA',
    'chartLabels' => $chartLabels,
    'chartData' => $chartData,
    'horizontal' => true,
]); ?>
<?php endif; ?>
