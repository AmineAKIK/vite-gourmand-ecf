<?php
// src/views/pages/employe/horaires.php
$pageTitle = buildPageTitle('Gestion des horaires');
?>
<div class="container py-5 employe-horaires-page">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-clock', 'title' => 'Gestion des horaires']); ?>

    <div class="card shadow-sm employe-horaires-card" style="border:1px solid rgba(0,0,0,.08); max-width:700px;">
        <form method="POST" action="/employe/horaires/modifier" novalidate>
            <?= csrfField() ?>

            <div class="table-responsive">
                <table class="table align-middle mb-0 employe-horaires-table" aria-label="Horaires d'ouverture">
                    <thead>
                        <tr style="background:rgba(0,0,0,.03); border-bottom:1px solid rgba(0,0,0,.08);">
                            <th scope="col" class="ps-3 text-vg fw-semibold">Jour</th>
                            <th scope="col" class="text-vg fw-semibold">Heure ouverture</th>
                            <th scope="col" class="text-vg fw-semibold pe-3">Heure fermeture</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horaires as $h): ?>
                        <tr>
                            <td data-label="Jour" class="fw-semibold ps-3 employe-horaire-day"><?= sanitize($h['jour']) ?></td>
                            <td data-label="Ouverture">
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    name="horaires[<?= (int)$h['horaire_id'] ?>][ouverture]"
                                    value="<?= sanitize($h['heure_ouverture'] ?? '') ?>"
                                    placeholder="Fermé"
                                    aria-label="Heure d'ouverture - <?= sanitize($h['jour']) ?>"
                                >
                            </td>
                            <td data-label="Fermeture" class="pe-3">
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

            <div class="d-flex gap-2 p-3 border-top employe-horaires-actions" style="border-color:rgba(0,0,0,.08)!important;">
                <button type="submit" class="btn btn-vg" aria-label="Enregistrer les horaires">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer les horaires
                </button>
                <a href="/employe/horaires" class="btn btn-vg-outline">Annuler</a>
            </div>
        </form>
    </div>

</div>
