<?php
$pageTitle = 'Espace Administrateur - Vite & Gourmand';

$totalCommandes = count($commandes ?? []);
$caTotal        = array_sum(array_column($stats ?? [], 'ca_total'));
$nbMenus        = count($stats ?? []);
$recent         = array_slice($commandes ?? [], 0, 3);

$chartLabels = array_map(fn($s) => $s['titre'] ?? '', $mongoStats ?? []);
$chartData   = array_column($mongoStats ?? [], 'nb_commandes');
?>

<div class="container py-4">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="fw-bold mb-0">
            <i class="bi bi-shield-check me-2 text-vg"></i>Espace Administrateur
        </h1>
        <span class="text-muted small">Connecté en tant qu'administrateur</span>
    </div>

    <!-- KPIs cliquables -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <a href="/employe/commandes" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-4 h-100 card-hover">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-receipt fs-2 text-vg"></i>
                        <div>
                            <div class="fs-2 fw-bold text-vg lh-1"><?= $totalCommandes ?></div>
                            <div class="text-muted small mt-1">Commandes totales</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4">
            <a href="/admin/stats" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-4 h-100 card-hover">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-cash-stack fs-2 text-vg"></i>
                        <div>
                            <div class="fs-2 fw-bold text-vg lh-1"><?= formatPrice($caTotal) ?></div>
                            <div class="text-muted small mt-1">Chiffre d'affaires total</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4">
            <a href="/employe/menus" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-4 h-100 card-hover">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-journal-text fs-2 text-vg"></i>
                        <div>
                            <div class="fs-2 fw-bold text-vg lh-1"><?= $nbMenus ?></div>
                            <div class="text-muted small mt-1">Menus actifs</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">

        <!-- Dernières commandes -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="bi bi-clock-history me-2 text-vg"></i>Dernières commandes</span>
                    <a href="/employe/commandes" class="btn btn-sm btn-vg-outline">Voir tout →</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent)): ?>
                        <p class="text-muted p-3 mb-0">Aucune commande.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>N°</th><th>Client</th><th>Menu</th><th>Date</th><th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $cmd): ?>
                                    <tr>
                                        <td><small class="text-muted"><?= sanitize($cmd['numero_commande'] ?? '') ?></small></td>
                                        <td><?= sanitize(personFullName($cmd)) ?></td>
                                        <td><?= sanitize($cmd['menu_titre'] ?? '') ?></td>
                                        <td><small><?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></small></td>
                                        <td><?= commandeStatusBadge($cmd['statut'] ?? null) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Graphique aperçu -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="bi bi-bar-chart me-2 text-vg"></i>Commandes par menu</span>
                    <a href="/admin/stats" class="btn btn-sm btn-vg-outline">Statistiques →</a>
                </div>
                <div class="card-body">
                    <div style="position:relative;height:220px">
                        <canvas id="chartMenus" aria-label="Graphique des commandes par menu" role="img"></canvas>
                    </div>
                    <p class="text-muted small text-center mt-2 mb-0">
                        <i class="bi bi-database me-1"></i>Données MongoDB Atlas
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- Raccourcis -->
    <h2 class="h6 fw-semibold text-muted text-uppercase mb-3" style="letter-spacing:.06em;">Accès rapide</h2>
    <div class="row g-3">
        <?php
        $shortcuts = [
            ['href' => '/admin/employes',   'icon' => 'bi-people',       'label' => 'Gérer les employés',      'desc' => 'Créer, désactiver, supprimer'],
            ['href' => '/employe/commandes','icon' => 'bi-list-check',   'label' => 'Toutes les commandes',    'desc' => 'Suivi et changement de statut'],
            ['href' => '/employe/menus',    'icon' => 'bi-journal-text', 'label' => 'Menus et plats',          'desc' => 'Ajouter, modifier, archiver'],
            ['href' => '/employe/avis',     'icon' => 'bi-star',         'label' => 'Avis clients',            'desc' => 'Valider ou refuser les avis'],
            ['href' => '/employe/horaires', 'icon' => 'bi-clock',        'label' => 'Horaires',                'desc' => 'Heures d\'ouverture du commerce'],
            ['href' => '/admin/accueil',    'icon' => 'bi-brush',        'label' => "Personnaliser l'accueil", 'desc' => 'Textes et images de la page d\'accueil'],
            ['href' => '/admin/parametres', 'icon' => 'bi-sliders',      'label' => 'Paramètres',              'desc' => 'Livraison, réductions'],
            ['href' => '/admin/stats',      'icon' => 'bi-graph-up',     'label' => 'Statistiques CA',         'desc' => 'Chiffre d\'affaires par menu et période'],
        ];
        ?>
        <?php foreach ($shortcuts as $s): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="<?= sanitize($s['href']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 h-100 card-hover">
                    <i class="bi <?= sanitize($s['icon']) ?> fs-4 text-vg mb-2"></i>
                    <div class="fw-semibold text-dark"><?= sanitize($s['label']) ?></div>
                    <div class="text-muted small"><?= sanitize($s['desc']) ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<?php partial('partials/chart_bar', [
    'chartId'     => 'chartMenus',
    'chartLabels' => $chartLabels,
    'chartData'   => $chartData,
]); ?>
