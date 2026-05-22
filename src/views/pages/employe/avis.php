<?php
// src/views/pages/employe/avis.php
$pageTitle = 'Modération des avis - Vite & Gourmand';
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-star', 'title' => 'Modération des avis']); ?>

    <?php if (empty($avis)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>Aucun avis en attente de validation.
        </div>
    <?php else: ?>
        <p class="text-muted mb-3"><?= count($avis) ?> avis en attente de validation.</p>

        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="Avis en attente de modération">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Note</th>
                        <th scope="col">Commentaire</th>
                        <th scope="col">Auteur</th>
                        <th scope="col">Menu</th>
                        <th scope="col">Date</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($avis as $a): ?>
                    <tr>
                        <!-- Étoiles -->
                        <td>
                            <span aria-label="Note : <?= (int)($a['note'] ?? 0) ?> étoiles sur 5">
                                <?php
                                $note = (int)($a['note'] ?? 0);
                                for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi <?= $i <= $note ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
                                <?php endfor; ?>
                            </span>
                        </td>

                        <!-- Commentaire -->
                        <td style="max-width:260px">
                            <span class="text-truncate d-block" title="<?= sanitize($a['description'] ?? '') ?>">
                                <?= !empty($a['description']) ? sanitize($a['description']) : '<em class="text-muted">Aucun commentaire</em>' ?>
                            </span>
                        </td>

                        <!-- Auteur -->
                        <td><?= sanitize(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? ''))) ?></td>

                        <!-- Menu -->
                        <td><?= sanitize($a['menu_titre'] ?? '—') ?></td>

                        <!-- Date -->
                        <td>
                            <small class="text-muted">
                                <?= !empty($a['created_at'])
                                    ? sanitize(formatDateTimeFr($a['created_at']))
                                    : '—' ?>
                            </small>
                        </td>

                        <!-- Actions -->
                        <td>
                            <div class="d-flex gap-2">
                                <!-- Valider -->
                                <form method="POST" action="/employe/avis/valider">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="commande_id" value="<?= (int)($a['commande_id'] ?? 0) ?>">
                                    <input type="hidden" name="action" value="valider">
                                    <button
                                        type="submit"
                                        class="btn btn-success btn-sm"
                                        aria-label="Valider l'avis de <?= sanitize(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? ''))) ?>"
                                    >
                                        <i class="bi bi-check-lg me-1"></i>Valider
                                    </button>
                                </form>

                                <!-- Refuser -->
                                <form method="POST" action="/employe/avis/valider" class="form-confirm">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="commande_id" value="<?= (int)($a['commande_id'] ?? 0) ?>">
                                    <input type="hidden" name="action" value="refuser">
                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-sm"
                                        aria-label="Refuser l'avis de <?= sanitize(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? ''))) ?>"
                                    >
                                        <i class="bi bi-x-lg me-1"></i>Refuser
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
