<?php
// src/views/pages/employe/horaires.php
$pageTitle = 'Gestion des horaires - Vite & Gourmand';
$dashboardUrl = hasRole('administrateur') ? '/admin' : '/employe';
$dashboardLabel = hasRole('administrateur') ? 'Espace administrateur' : 'Espace employé';
?>
<div class="container py-5">

    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= $dashboardUrl ?>"><?= $dashboardLabel ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Horaires</li>
        </ol>
    </nav>

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= $dashboardUrl ?>" class="btn btn-outline-secondary btn-sm" aria-label="Retour au tableau de bord">
            <i class="bi bi-arrow-left me-1"></i>Tableau de bord
        </a>
        <h1 class="h3 fw-bold mb-0">
            <i class="bi bi-clock me-2 text-vg"></i>Gestion des horaires
        </h1>
    </div>

    <div class="card border-0 shadow-sm p-4" style="max-width:700px">
        <form method="POST" action="/employe/horaires/modifier" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

            <div class="table-responsive">
                <table class="table align-middle" aria-label="Horaires d'ouverture">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Jour</th>
                            <th scope="col">Heure ouverture</th>
                            <th scope="col">Heure fermeture</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horaires as $h): ?>
                        <tr>
                            <td class="fw-semibold"><?= sanitize($h['jour']) ?></td>
                            <td>
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    name="horaires[<?= (int)$h['horaire_id'] ?>][ouverture]"
                                    value="<?= sanitize($h['heure_ouverture'] ?? '') ?>"
                                    placeholder="Fermé"
                                    aria-label="Heure d'ouverture - <?= sanitize($h['jour']) ?>"
                                >
                            </td>
                            <td>
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    name="horaires[<?= (int)$h['horaire_id'] ?>][fermeture]"
                                    value="<?= sanitize($h['heure_fermeture'] ?? '') ?>"
                                    placeholder="18:00"
                                    aria-label="Heure de fermeture - <?= sanitize($h['jour']) ?>"
                                >
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-vg" aria-label="Enregistrer les horaires">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer les horaires
                </button>
                <a href="<?= $dashboardUrl ?>" class="btn btn-outline-secondary">Annuler</a>
            </div>
        </form>
    </div>

</div>
