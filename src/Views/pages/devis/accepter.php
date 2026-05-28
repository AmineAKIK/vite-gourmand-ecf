<?php
$pageTitle = buildPageTitle('Acceptation de devis');
$numero    = sanitize($document['numero_document'] ?? ('Devis #' . (int)($document['document_id'] ?? 0)));
$nomClient = sanitize($document['client_nom'] ?? '');
$total     = formatPrice($document['total_ttc'] ?? 0);
$dateEmis  = formatDateFr($document['date_emission'] ?? null);
?>

<div class="container py-5" style="max-width:640px">
    <?php if ($alreadySigned): ?>
        <div class="text-center py-5">
            <div style="font-size:4rem;color:#10b981">✓</div>
            <h1 class="h3 mt-3 fw-bold">Devis accepté</h1>
            <p class="text-muted mt-2">
                Ce devis a déjà été signé le <?= sanitize(formatDateTimeFr($document['signed_at'])) ?>.
            </p>
            <?php displayFlash(); ?>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h4 fw-bold mb-1">Acceptation du devis</h1>
                <p class="text-muted mb-4">Référence : <strong><?= $numero ?></strong> — émis le <?= $dateEmis ?></p>

                <?php displayFlash(); ?>

                <dl class="row g-2 mb-4">
                    <dt class="col-5 text-muted">Client</dt>
                    <dd class="col-7 fw-semibold"><?= $nomClient ?></dd>
                    <dt class="col-5 text-muted">Montant TTC</dt>
                    <dd class="col-7 fw-semibold" style="color:var(--vg-primary, #8B1A2B)"><?= sanitize($total) ?></dd>
                    <?php if (!empty($document['date_emission'])): ?>
                    <dt class="col-5 text-muted">Valable jusqu'au</dt>
                    <dd class="col-7"><?= sanitize(date('d/m/Y', strtotime($document['date_emission'] . ' +30 days'))) ?></dd>
                    <?php endif; ?>
                </dl>

                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    En cliquant sur « J'accepte ce devis », vous confirmez avoir pris connaissance du document
                    et l'accepter sans réserve. Votre acceptation sera horodatée et votre adresse IP enregistrée.
                </div>

                <form method="POST">
                    <?= csrfField() ?>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="confirmation" required>
                        <label class="form-check-label" for="confirmation">
                            Je confirme avoir lu le devis et je l'accepte sans réserve.
                        </label>
                    </div>
                    <button type="submit" class="btn btn-vg btn-lg w-100">
                        <i class="bi bi-pen me-2"></i>J'accepte ce devis
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
