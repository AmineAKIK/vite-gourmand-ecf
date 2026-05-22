<?php
// src/views/pages/employe/dashboard.php
$pageTitle = 'Espace Employé - Vite & Gourmand';

$user        = currentUser();
$nbAttente   = commandeCountByStatus($commandes, commandeInitialStatus());
$nbPrepa     = commandeCountByStatus($commandes, commandePreparingStatus());
$nbLivraison = commandeCountByStatus($commandes, commandeDeliveryStatus());
$dernieres   = array_slice($commandes, 0, 10);
?>
<div class="container py-5">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="fw-bold mb-0">
            <i class="bi bi-person-gear me-2 text-vg"></i>
            Espace Employé — Bonjour <?= sanitize($user['prenom'] ?? '') ?> !
        </h1>
        <span class="text-muted small">Connecté en tant qu'employé</span>
    </div>

    <?php partial('partials/workspace_nav'); ?>

    <!-- Cards de statistiques -->
    <div class="row g-3 mb-5">
        <?php partial('partials/stat_card', ['value' => $nbAttente, 'valueClass' => 'text-warning', 'icon' => 'bi-hourglass-split', 'label' => 'En attente']); ?>
        <?php partial('partials/stat_card', ['value' => $nbPrepa, 'valueClass' => 'text-primary', 'icon' => 'bi-tools', 'label' => 'En préparation']); ?>
        <?php partial('partials/stat_card', ['value' => $nbLivraison, 'valueClass' => 'text-info', 'icon' => 'bi-truck', 'label' => 'En cours de livraison']); ?>
    </div>

    <!-- Tableau des 10 dernières commandes -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">10 dernières commandes</h2>
        <a href="/employe/commandes" class="btn btn-outline-secondary btn-sm">Voir tout</a>
    </div>

    <?php if (empty($dernieres)): ?>
        <div class="alert alert-info">Aucune commande à afficher.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="Dernières commandes">
                <thead class="table-light">
                    <tr>
                        <th scope="col">N° commande</th>
                        <th scope="col">Client</th>
                        <th scope="col">Menu</th>
                        <th scope="col">Date prestation</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dernieres as $cmd): ?>
                    <tr>
                        <td><small class="text-muted"><?= sanitize($cmd['numero_commande'] ?? '') ?></small></td>
                        <td><?= sanitize(personFullName($cmd)) ?></td>
                        <td><?= sanitize($cmd['menu_titre'] ?? '') ?></td>
                        <td>
                            <?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?>
                        </td>
                        <td><?= commandeStatusBadge($cmd['statut'] ?? null) ?></td>
                        <td>
                            <a href="/employe/commandes"
                               class="btn btn-sm btn-vg"
                               aria-label="Gérer la commande <?= sanitize($cmd['numero_commande'] ?? '') ?>">
                                <i class="bi bi-pencil-square me-1"></i>Gérer
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
