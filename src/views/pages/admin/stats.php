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

    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <a href="/admin" class="btn btn-outline-secondary btn-sm" aria-label="Retour au tableau de bord administrateur">
            <i class="bi bi-arrow-left me-1"></i>Tableau de bord
        </a>
        <h1 class="h3 fw-bold mb-0">
            <i class="bi bi-graph-up me-2 text-vg"></i>Statistiques — Chiffre d'affaires
        </h1>
    </div>

    <!-- Formulaire de filtres -->
    <div class="filtres-panel card border-0 shadow-sm p-3 mb-4">
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
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-vg btn-sm flex-grow-1" aria-label="Filtrer les statistiques">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
                <a href="/admin/stats" class="btn btn-outline-secondary btn-sm" aria-label="Réinitialiser les filtres">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="row g-4">

        <!-- Tableau CA par menu -->
        <div class="col-lg-6">
            <h2 class="h5 fw-bold mb-3">CA par menu</h2>
            <?php if (empty($caStats)): ?>
                <div class="alert alert-info">Aucune donnée pour cette période.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" aria-label="Chiffre d'affaires par menu">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Menu</th>
                                <th scope="col" class="text-end">Nb commandes</th>
                                <th scope="col" class="text-end">CA total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($caStats as $row): ?>
                            <tr>
                                <td><?= sanitize($row['titre']) ?></td>
                                <td class="text-end"><?= number_format($row['nb'], 0) ?></td>
                                <td class="text-end fw-semibold">
                                    <?= number_format($row['ca'], 2) ?> €
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <!-- Ligne total -->
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td>TOTAL</td>
                                <td class="text-end"><?= $totalNb ?></td>
                                <td class="text-end"><?= number_format($totalCA, 2) ?> €</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Graphique barres horizontales -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-3 h-100">
                <h2 class="h6 fw-bold mb-3">
                    <i class="bi bi-bar-chart-horizontal me-2 text-vg"></i>Commandes par menu (MongoDB)
                </h2>
                <?php if (empty($mongoStats)): ?>
                    <p class="text-muted small">Aucune donnée à afficher.</p>
                <?php else: ?>
                    <div style="position:relative;height:300px">
                        <canvas id="chartCA" aria-label="Graphique du chiffre d'affaires par menu" role="img"></canvas>
                    </div>
                    <p class="text-muted small text-center mt-2">
                        <i class="bi bi-database me-1"></i>Données issues de MongoDB
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /row -->

</div>

<?php if (!empty($mongoStats)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" integrity="sha512-ZwR1/gSZM3ai6vCdI+LVF1zSq/5HznD3oD+sCoJrzXJ+yKwtkiTap5sVAArNg2b/LTNqcrh11PC3w7TnCdXiQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
    var ctx = document.getElementById('chartCA');
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
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.parsed.x + ' commande(s)';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (val) {
                            return val.toFixed(0);
                        }
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>
