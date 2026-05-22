<?php
// src/views/pages/employe/horaires.php
$pageTitle = 'Gestion des horaires - Vite & Gourmand';
?>
<div class="container py-5">

    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= sanitize(roleHomePath()) ?>"><?= sanitize(roleHomeLabel()) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Horaires</li>
        </ol>
    </nav>

    <?php partial('partials/page_title_bar', ['icon' => 'bi-clock', 'title' => 'Gestion des horaires']); ?>

    <div class="card border-0 shadow-sm p-4" style="max-width:700px">
        <form method="POST" action="/employe/horaires/modifier" novalidate>
            <?= csrfField() ?>

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
                <a href="<?= sanitize(roleHomePath()) ?>" class="btn btn-outline-secondary">Annuler</a>
            </div>
        </form>
    </div>

</div>
