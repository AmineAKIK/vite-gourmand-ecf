<?php
// src/views/pages/employe/avis.php
$pageTitle = 'Modération des avis - Vite & Gourmand';
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-star', 'title' => 'Modération des avis']); ?>

    <!-- Onglets filtre -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $filtre === 'en_attente' ? 'active fw-bold' : '' ?>" href="/employe/avis?filtre=en_attente">
                En attente
                <?php if (!empty($pending)): ?>
                    <span class="badge bg-danger ms-1"><?= count($pending) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filtre === 'valide' ? 'active fw-bold' : '' ?>" href="/employe/avis?filtre=valide">Validés</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filtre === 'refuse' ? 'active fw-bold' : '' ?>" href="/employe/avis?filtre=refuse">Refusés</a>
        </li>
    </ul>

    <?php if (empty($avis)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <?php if ($filtre === 'en_attente'): ?>
                Aucun avis en attente de validation.
            <?php elseif ($filtre === 'valide'): ?>
                Aucun avis validé.
            <?php else: ?>
                Aucun avis refusé.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-muted mb-3"><?= count($avis) ?> avis.</p>
        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="Liste des avis">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Note</th>
                        <th scope="col">Commentaire</th>
                        <th scope="col">Auteur</th>
                        <th scope="col">Menu</th>
                        <th scope="col">Date</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($avis as $a): ?>
                    <tr>
                        <td>
                            <span aria-label="Note : <?= (int)($a['note'] ?? 0) ?> étoiles sur 5">
                                <?php $note = (int)($a['note'] ?? 0);
                                for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi <?= $i <= $note ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
                                <?php endfor; ?>
                            </span>
                        </td>
                        <td style="max-width:260px">
                            <span class="text-truncate d-block" title="<?= sanitize($a['description'] ?? '') ?>">
                                <?= !empty($a['description']) ? sanitize($a['description']) : '<em class="text-muted">Aucun commentaire</em>' ?>
                            </span>
                        </td>
                        <td><?= sanitize(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? ''))) ?></td>
                        <td><?= sanitize($a['menu_titre'] ?? '—') ?></td>
                        <td><small class="text-muted"><?= !empty($a['created_at']) ? sanitize(formatDateTimeFr($a['created_at'])) : '—' ?></small></td>
                        <td>
                            <?php if ($a['statut'] === 'valide'): ?>
                                <span class="badge bg-success">Validé</span>
                            <?php elseif ($a['statut'] === 'refuse'): ?>
                                <span class="badge bg-danger">Refusé</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">En attente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if ($a['statut'] !== 'valide'): ?>
                                <form method="POST" action="/employe/avis/valider">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="commande_id" value="<?= (int)($a['commande_id'] ?? 0) ?>">
                                    <input type="hidden" name="action" value="valider">
                                    <input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-lg me-1"></i>Valider
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($a['statut'] !== 'refuse'): ?>
                                <form method="POST" action="/employe/avis/valider" class="form-confirm">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="commande_id" value="<?= (int)($a['commande_id'] ?? 0) ?>">
                                    <input type="hidden" name="action" value="refuser">
                                    <input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-x-lg me-1"></i>Refuser
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
