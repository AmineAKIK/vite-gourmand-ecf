<?php
// src/views/pages/employe/avis.php
$pageTitle = 'Modération des avis - Vite & Gourmand';
?>
<div class="container py-5">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-star', 'title' => 'Modération des avis']); ?>

    <?php if (!empty($doublonsAccueil)): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
            <div>
                <strong>Attention :</strong>
                plusieurs avis mis en avant sur l'accueil viennent du même client
                (<?= sanitize(implode(', ', array_map(
                    fn($client) => trim(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? '')) . ' x' . (int)($client['total'] ?? 0),
                    $doublonsAccueil
                ))) ?>).
            </div>
        </div>
    <?php endif; ?>

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
        <div class="avis-moderation-list">
            <?php foreach ($avis as $a): ?>
            <?php $note = (int)($a['note'] ?? 0); ?>
            <div class="avis-moderation-card">

                <!-- Ligne 1 : étoiles + auteur + menu + date -->
                <div class="avis-moderation-meta">
                    <span class="avis-moderation-stars" aria-label="<?= $note ?> étoiles sur 5">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi <?= $i <= $note ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>" aria-hidden="true"></i>
                        <?php endfor; ?>
                    </span>
                    <span class="avis-moderation-author fw-semibold"><?= sanitize(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? ''))) ?></span>
                    <span class="text-muted">·</span>
                    <span class="text-muted small"><?= sanitize($a['menu_titre'] ?? '—') ?></span>
                    <span class="text-muted">·</span>
                    <span class="text-muted small"><?= !empty($a['created_at']) ? sanitize(formatDateTimeFr($a['created_at'])) : '—' ?></span>

                    <span class="ms-auto">
                        <?php if ($a['statut'] === 'valide'): ?>
                            <span class="badge bg-success">Validé</span>
                            <?php if (!empty($a['afficher_accueil'])): ?>
                                <span class="badge bg-info ms-1">Accueil</span>
                            <?php endif; ?>
                        <?php elseif ($a['statut'] === 'refuse'): ?>
                            <span class="badge bg-danger">Refusé</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">En attente</span>
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Ligne 2 : commentaire -->
                <p class="avis-moderation-comment">
                    <?php if (!empty($a['description'])): ?>
                        <?= htmlspecialchars(html_entity_decode(trim($a['description']), ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') ?>
                    <?php else: ?>
                        <em class="text-muted">Aucun commentaire</em>
                    <?php endif; ?>
                </p>

                <!-- Ligne 3 : actions -->
                <div class="avis-moderation-actions">
                    <?php if ($a['statut'] === 'valide'): ?>
                    <form method="POST" action="/employe/avis/accueil">
                        <?= csrfField() ?>
                        <input type="hidden" name="avis_id" value="<?= (int)($a['avis_id'] ?? 0) ?>">
                        <input type="hidden" name="afficher_accueil" value="<?= !empty($a['afficher_accueil']) ? '0' : '1' ?>">
                        <input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>">
                        <button type="submit" class="btn <?= !empty($a['afficher_accueil']) ? 'btn-outline-secondary' : 'btn-vg-outline' ?> btn-sm">
                            <i class="bi <?= !empty($a['afficher_accueil']) ? 'bi-eye-slash' : 'bi-house-heart' ?> me-1"></i>
                            <?= !empty($a['afficher_accueil']) ? 'Retirer de l\'accueil' : 'Afficher sur l\'accueil' ?>
                        </button>
                    </form>
                    <?php endif; ?>
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
                    <form method="POST" action="/employe/avis/supprimer" class="form-confirm ms-auto">
                        <?= csrfField() ?>
                        <input type="hidden" name="avis_id" value="<?= (int)($a['avis_id'] ?? 0) ?>">
                        <input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Supprimer définitivement cet avis">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
