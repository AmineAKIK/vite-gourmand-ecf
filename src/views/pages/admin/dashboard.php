<?php
// src/views/pages/admin/dashboard.php
$pageTitle = 'Espace Administrateur - Vite & Gourmand';

$totalCommandes = count($commandes ?? []);
$caTotal        = array_sum(array_column($stats ?? [], 'ca_total'));
$nbMenus        = count($stats ?? []);

/* Prépare les données MongoDB pour Chart.js */
$chartLabels = array_map(fn($s) => addslashes($s['titre'] ?? ''), $mongoStats ?? []);
$chartData   = array_column($mongoStats ?? [], 'nb_commandes');
?>
<div class="container py-5">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="fw-bold mb-0">
            <i class="bi bi-shield-check me-2 text-vg"></i>Espace Administrateur
        </h1>
        <span class="text-muted small">Connecté en tant qu'administrateur</span>
    </div>

    <!-- Navigation rapide -->
    <nav class="mb-4" aria-label="Navigation administrateur">
        <div class="d-flex flex-wrap gap-2">
            <a href="/admin/employes" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people me-1"></i>Gérer les employés
            </a>
            <a href="/admin/stats" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-graph-up me-1"></i>Statistiques CA
            </a>
            <a href="/employe/commandes" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-check me-1"></i>Toutes les commandes
            </a>
            <a href="/employe/menus" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-journal-text me-1"></i>Menus et plats
            </a>
            <a href="/employe/avis" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-star me-1"></i>Avis
            </a>
            <a href="/employe/horaires" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock me-1"></i>Horaires
            </a>
        </div>
    </nav>

    <!-- Cards de statistiques globales -->
    <div class="row g-3 mb-5">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <div class="display-6 fw-bold text-vg"><?= $totalCommandes ?></div>
                <div class="small text-muted mt-1">
                    <i class="bi bi-receipt me-1"></i>Commandes totales
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <div class="display-6 fw-bold text-success">
                    <?= number_format($caTotal, 2, ',', ' ') ?> €
                </div>
                <div class="small text-muted mt-1">
                    <i class="bi bi-cash-stack me-1"></i>CA total
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <div class="display-6 fw-bold text-primary"><?= $nbMenus ?></div>
                <div class="small text-muted mt-1">
                    <i class="bi bi-journal-text me-1"></i>Menus actifs
                </div>
            </div>
        </div>
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
                                <td><?= sanitize(trim(($cmd['prenom'] ?? '') . ' ' . ($cmd['nom'] ?? ''))) ?></td>
                                <td><?= sanitize($cmd['menu_titre'] ?? '') ?></td>
                                <td>
                                    <small><?= !empty($cmd['date_prestation']) ? date('d/m/Y', strtotime($cmd['date_prestation'])) : '—' ?></small>
                                </td>
                                <td>
                                    <span class="badge-statut statut-<?= sanitize($cmd['statut'] ?? '') ?>">
                                        <?= sanitize(str_replace('_', ' ', $cmd['statut'] ?? '')) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Graphique commandes par menu -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-3 h-100">
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" integrity="sha512-ZwR1/gSZM3ai6vCdI+LVF1zSq/5HznD3oD+sCoJrzXJ+yKwtkiTap5sVAArNg2b/LTNqcrh11PC3w7TnCdXiQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
    var ctx = document.getElementById('chartMenus');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Nb commandes',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(114,47,55,0.75)',
                borderColor: 'rgba(114,47,55,1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
})();
</script>
