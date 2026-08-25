<?php
$pageTitle = buildPageTitle('Modération des avis');
?>
<div class="container py-5">
    <?php partial('partials/page_title_bar', ['icon' => 'bi-star', 'title' => 'Modération des avis']); ?>

    <?php if (!empty($doublonsAccueil)): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
            <div><strong>Attention :</strong> plusieurs avis mis en avant sur l'accueil viennent du même client (<?= sanitize(implode(', ', array_map(fn($client) => trim(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? '')) . ' x' . (int) ($client['total'] ?? 0), $doublonsAccueil))) ?>).</div>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?= $filtre === 'en_attente' ? 'active fw-bold' : '' ?>" href="/employe/avis?filtre=en_attente">En attente <?php if (!empty($pending)): ?><span class="badge bg-danger ms-1"><?= count($pending) ?></span><?php endif; ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $filtre === 'valide' ? 'active fw-bold' : '' ?>" href="/employe/avis?filtre=valide">Validés</a></li>
        <li class="nav-item"><a class="nav-link <?= $filtre === 'refuse' ? 'active fw-bold' : '' ?>" href="/employe/avis?filtre=refuse">Refusés</a></li>
    </ul>

    <?php if (empty($avis)): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><?= $filtre === 'en_attente' ? 'Aucun avis en attente de validation.' : ($filtre === 'valide' ? 'Aucun avis validé.' : 'Aucun avis refusé.') ?></div>
    <?php else: ?>
        <p class="text-muted mb-3"><?= count($avis) ?> avis.</p>
        <div class="d-grid gap-3">
            <?php foreach ($avis as $a): ?>
            <?php $note = (int) ($a['note'] ?? 0); ?>
            <article class="card p-3">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span aria-label="<?= $note ?> étoiles sur 5"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= $note ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>" aria-hidden="true"></i><?php endfor; ?></span>
                    <strong><?= sanitize(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? ''))) ?></strong>
                    <span class="text-muted small"><?= sanitize($a['menu_titre'] ?? '—') ?> · <?= !empty($a['created_at']) ? sanitize(formatDateTimeFr($a['created_at'])) : '—' ?></span>
                    <span class="ms-auto"><?php if ($a['statut'] === 'valide'): ?><span class="badge bg-success">Validé</span><?php if (!empty($a['afficher_accueil'])): ?><span class="badge bg-info ms-1">Accueil</span><?php endif; ?><?php elseif ($a['statut'] === 'refuse'): ?><span class="badge bg-danger">Refusé</span><?php else: ?><span class="badge bg-warning text-dark">En attente</span><?php endif; ?></span>
                </div>
                <p><?php if (!empty($a['description'])): ?><?= htmlspecialchars(html_entity_decode(trim($a['description']), ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') ?><?php else: ?><em class="text-muted">Aucun commentaire</em><?php endif; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($a['statut'] === 'valide'): ?>
                    <form method="POST" action="/employe/avis/accueil"><?= csrfField() ?><input type="hidden" name="avis_id" value="<?= (int) ($a['avis_id'] ?? 0) ?>"><input type="hidden" name="afficher_accueil" value="<?= !empty($a['afficher_accueil']) ? '0' : '1' ?>"><input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>"><button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi <?= !empty($a['afficher_accueil']) ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i><?= !empty($a['afficher_accueil']) ? 'Masquer' : 'Afficher' ?></button></form>
                    <?php endif; ?>
                    <?php if ($a['statut'] !== 'valide'): ?><form method="POST" action="/employe/avis/valider"><?= csrfField() ?><input type="hidden" name="commande_id" value="<?= (int) ($a['commande_id'] ?? 0) ?>"><input type="hidden" name="action" value="valider"><input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>"><button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Valider</button></form><?php endif; ?>
                    <?php if ($a['statut'] !== 'refuse'): ?><form method="POST" action="/employe/avis/valider" class="form-confirm"><?= csrfField() ?><input type="hidden" name="commande_id" value="<?= (int) ($a['commande_id'] ?? 0) ?>"><input type="hidden" name="action" value="refuser"><input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>"><button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg me-1"></i>Refuser</button></form><?php endif; ?>
                    <form method="POST" action="/employe/avis/supprimer" class="form-confirm ms-auto"><?= csrfField() ?><input type="hidden" name="avis_id" value="<?= (int) ($a['avis_id'] ?? 0) ?>"><input type="hidden" name="filtre" value="<?= sanitize($filtre) ?>"><button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Supprimer définitivement cet avis"><i class="bi bi-trash"></i></button></form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
