<?php
// src/views/pages/admin/dashboard.php
$pageTitle = 'Espace Administrateur - Vite & Gourmand';

$totalCommandes = count($commandes ?? []);
$caTotal        = array_sum(array_column($stats ?? [], 'ca_total'));
$nbMenus        = count($stats ?? []);

/* Prépare les données MongoDB pour Chart.js */
$chartLabels = array_map(fn($s) => $s['titre'] ?? '', $mongoStats ?? []);
$chartData   = array_column($mongoStats ?? [], 'nb_commandes');
?>
<div class="container py-5">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="fw-bold mb-0">
            <i class="bi bi-shield-check me-2 text-vg"></i>Espace Administrateur
        </h1>
        <span class="text-muted small">Connecté en tant qu'administrateur</span>
    </div>

    <?php partial('partials/workspace_nav'); ?>

    <!-- Cards de statistiques globales -->
    <div class="row g-3 mb-5">
        <?php partial('partials/stat_card', ['value' => $totalCommandes, 'valueClass' => 'text-vg', 'icon' => 'bi-receipt', 'label' => 'Commandes totales']); ?>
        <?php partial('partials/stat_card', ['value' => formatPrice($caTotal), 'valueClass' => 'text-success', 'icon' => 'bi-cash-stack', 'label' => 'CA total']); ?>
        <?php partial('partials/stat_card', ['value' => $nbMenus, 'valueClass' => 'text-primary', 'icon' => 'bi-journal-text', 'label' => 'Menus actifs']); ?>
    </div>

    <div class="row g-4 mb-5">

        <!-- Tableau des commandes récentes -->
        <div class="col-lg-7">
            <h2 class="h5 fw-bold mb-3">Commandes récentes</h2>
            <?php if (empty($commandes)): ?>
                <div class="alert alert-info">Aucune commande.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle" aria-label="Commandes récentes">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">N°</th>
                                <th scope="col">Client</th>
                                <th scope="col">Menu</th>
                                <th scope="col">Date</th>
                                <th scope="col">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $cmd): ?>
                            <tr>
                                <td><small class="text-muted"><?= sanitize($cmd['numero_commande'] ?? '') ?></small></td>
                                <td><?= sanitize(personFullName($cmd)) ?></td>
                                <td><?= sanitize($cmd['menu_titre'] ?? '') ?></td>
                                <td>
                                    <small><?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></small>
                                </td>
                                <td><?= commandeStatusBadge($cmd['statut'] ?? null) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Graphique commandes par menu -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-3 h-100" style="background:var(--vg-creme);">
                <h2 class="h6 fw-bold mb-3">
                    <i class="bi bi-bar-chart me-2 text-vg"></i>Commandes par menu
                </h2>
                <div style="position:relative;height:280px">
                    <canvas id="chartMenus" aria-label="Graphique des commandes par menu" role="img"></canvas>
                </div>
                <p class="text-muted small text-center mt-2 mb-0">
                    <i class="bi bi-database me-1"></i>Données issues de MongoDB Atlas
                </p>
            </div>
        </div>

    </div><!-- /row -->

</div>

<?php partial('partials/chart_bar', [
    'chartId' => 'chartMenus',
    'chartLabels' => $chartLabels,
    'chartData' => $chartData,
]); ?>
