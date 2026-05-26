<?php
// src/views/pages/admin/employes.php
$pageTitle = buildPageTitle('Gestion des employés');
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-people', 'title' => 'Gestion des employés']); ?>

    <div class="row g-4">

        <!-- Formulaire création employé -->
        <div class="col-lg-4">
            <div class="card shadow-sm" style="border:1px solid rgba(0,0,0,.08);">
                <div class="card-header fw-semibold" style="background:rgba(0,0,0,.03); border-bottom:1px solid rgba(0,0,0,.08);">
                    <i class="bi bi-person-plus me-2 text-vg"></i>Ajouter un employé
                </div>
                <div class="card-body p-4">
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
        </div>

        <!-- Liste des employés -->
        <div class="col-lg-8">
            <?php if (empty($employes)): ?>
                <div class="alert alert-info">Aucun employé enregistré.</div>
            <?php else: ?>
                <div class="employe-list">
                    <?php foreach ($employes as $employe): ?>
                    <div class="employe-card">
                        <div class="employe-card-info">
                            <span class="fw-semibold"><?= sanitize(personFullName($employe)) ?></span>
                            <span class="text-muted small"><?= sanitize($employe['email'] ?? '') ?></span>
                        </div>
                        <div class="employe-card-actions">
                            <?php if ($employe['actif'] ?? false): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                            <form method="POST" action="/admin/employe/desactiver" class="form-confirm">
                                <?= csrfField() ?>
                                <input type="hidden" name="employe_id" value="<?= (int)($employe['utilisateur_id'] ?? 0) ?>">
                                <input type="hidden" name="actif" value="<?= ($employe['actif'] ?? false) ? '0' : '1' ?>">
                                <?php if ($employe['actif'] ?? false): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-person-x me-1"></i>Désactiver
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-person-check me-1"></i>Réactiver
                                    </button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" action="/admin/employe/supprimer" class="form-confirm">
                                <?= csrfField() ?>
                                <input type="hidden" name="employe_id" value="<?= (int)($employe['utilisateur_id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    aria-label="Supprimer définitivement le compte de <?= sanitize(personFullName($employe)) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /row -->

</div>
