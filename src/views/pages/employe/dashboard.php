<?php
// src/views/pages/employe/dashboard.php
$pageTitle = 'Espace Employé - Vite & Gourmand';

$user        = currentUser();
$nbAttente   = count(array_filter($commandes, fn($c) => $c['statut'] === 'en_attente'));
$nbPrepa     = count(array_filter($commandes, fn($c) => $c['statut'] === 'en_preparation'));
$nbLivraison = count(array_filter($commandes, fn($c) => $c['statut'] === 'en_cours_livraison'));
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

    <!-- Navigation rapide -->
    <nav class="mb-4" aria-label="Navigation employé">
        <div class="d-flex flex-wrap gap-2">
            <a href="/employe/commandes" class="btn btn-vg btn-sm">
                <i class="bi bi-list-check me-1"></i>Commandes
            </a>
            <a href="/employe/menus" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-journal-text me-1"></i>Menus
            </a>
            <a href="/employe/avis" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-star me-1"></i>Avis
            </a>
            <a href="/employe/horaires" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock me-1"></i>Horaires
            </a>
        </div>
    </nav>

    <!-- Cards de statistiques -->
    <div class="row g-3 mb-5">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <div class="display-6 fw-bold text-warning"><?= $nbAttente ?></div>
                <div class="small text-muted mt-1">
                    <i class="bi bi-hourglass-split me-1"></i>En attente
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <div class="display-6 fw-bold text-primary"><?= $nbPrepa ?></div>
                <div class="small text-muted mt-1">
                    <i class="bi bi-tools me-1"></i>En préparation
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <div class="display-6 fw-bold text-info"><?= $nbLivraison ?></div>
                <div class="small text-muted mt-1">
                    <i class="bi bi-truck me-1"></i>En cours de livraison
                </div>
            </div>
        </div>
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
                        <td><?= sanitize(trim(($cmd['prenom'] ?? '') . ' ' . ($cmd['nom'] ?? ''))) ?></td>
                        <td><?= sanitize($cmd['menu_titre'] ?? '') ?></td>
                        <td>
                            <?= !empty($cmd['date_prestation'])
                                ? date('d/m/Y', strtotime($cmd['date_prestation']))
                                : '—' ?>
                        </td>
                        <td>
                            <span class="badge-statut statut-<?= sanitize($cmd['statut'] ?? '') ?>">
                                <?= sanitize(str_replace('_', ' ', $cmd['statut'] ?? '')) ?>
                            </span>
                        </td>
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
