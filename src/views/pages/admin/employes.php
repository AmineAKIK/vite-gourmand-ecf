<?php
// src/views/pages/admin/employes.php
$pageTitle = 'Gestion des employés - Vite & Gourmand';
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-people', 'title' => 'Gestion des employés']); ?>

    <div class="row g-4">

        <!-- Formulaire création employé -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4">
                <h2 class="h5 fw-bold mb-3">
                    <i class="bi bi-person-plus me-2 text-vg"></i>Ajouter un employé
                </h2>
                <form method="POST" action="/admin/employe/creer" novalidate>
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="emp-email" class="form-label">Adresse email <span class="text-danger" aria-hidden="true">*</span></label>
                        <input
                            type="email"
                            class="form-control"
                            id="emp-email"
                            name="email"
                            required
                            aria-required="true"
                            autocomplete="off"
                            placeholder="prenom.nom@vitegourmand.fr"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="emp-prenom" class="form-label">Prénom <span class="text-danger" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="emp-prenom"
                            name="prenom"
                            required
                            aria-required="true"
                            maxlength="100"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="emp-nom" class="form-label">Nom <span class="text-danger" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="emp-nom"
                            name="nom"
                            required
                            aria-required="true"
                            maxlength="100"
                        >
                    </div>

                    <div class="mb-4">
                        <label for="emp-password" class="form-label">Mot de passe temporaire <span class="text-danger" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <input
                                type="password"
                                class="form-control"
                                id="emp-password"
                                name="password"
                                required
                                aria-required="true"
                                autocomplete="new-password"
                            >
                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                data-password-toggle="emp-password"
                                aria-label="Afficher ou masquer le mot de passe"
                            >
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text"><?= sanitize(passwordPolicyMessage()) ?></div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-vg" aria-label="Créer le compte employé">
                            <i class="bi bi-person-plus me-1"></i>Créer le compte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des employés -->
        <div class="col-lg-8">
            <h2 class="h5 fw-bold mb-3">Employés enregistrés</h2>

            <?php if (empty($employes)): ?>
                <div class="alert alert-info">Aucun employé enregistré.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" aria-label="Liste des employés">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Prénom / Nom</th>
                                <th scope="col">Email</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                                    <?php foreach ($employes as $employe): ?>
                            <tr>
                                <td><?= sanitize(personFullName($employe)) ?></td>
                                <td><?= sanitize($employe['email'] ?? '') ?></td>
                                <td>
                                    <?php if ($employe['actif'] ?? false): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="/admin/employe/desactiver" class="form-confirm">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="employe_id" value="<?= (int)($employe['utilisateur_id'] ?? 0) ?>">
                                        <input type="hidden" name="actif" value="<?= ($employe['actif'] ?? false) ? '0' : '1' ?>">

                                        <?php if ($employe['actif'] ?? false): ?>
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                aria-label="Désactiver le compte de <?= sanitize(personFullName($employe)) ?>"
                                            >
                                                <i class="bi bi-person-x me-1"></i>Désactiver
                                            </button>
                                        <?php else: ?>
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-success"
                                                aria-label="Réactiver le compte de <?= sanitize(personFullName($employe)) ?>"
                                            >
                                                <i class="bi bi-person-check me-1"></i>Réactiver
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /row -->

</div>
