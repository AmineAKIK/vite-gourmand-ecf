<?php
$pageTitle = 'Paramètres — Vite & Gourmand';
$cspNonce  = $GLOBALS['csp_nonce'] ?? '';

$cfg = function(string $cle, string $default = '') use ($config): string {
    return sanitize($config[$cle] ?? $default);
};
?>

<?php partial('partials/page_title_bar', ['icon' => 'bi-sliders', 'title' => 'Paramètres']); ?>

<div class="row g-4">

    <!-- Frais de livraison -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-truck me-2 text-vg"></i>Frais de livraison
            </div>
            <div class="card-body">
                <form method="post" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="hero_sous_titre"  value="<?= $cfg('hero_sous_titre') ?>">
                    <input type="hidden" name="hero_paragraphe"  value="<?= $cfg('hero_paragraphe') ?>">
                    <input type="hidden" name="reduction_seuil"  value="<?= $cfg('reduction_seuil', '100.00') ?>">
                    <input type="hidden" name="reduction_taux"   value="<?= $cfg('reduction_taux',  '10') ?>">

                    <div class="mb-3">
                        <label class="form-label fw-medium" for="livraison_base">Frais fixes (€)</label>
                        <div class="input-group">
                            <input
                                type="number"
                                id="livraison_base"
                                name="livraison_base"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= $cfg('livraison_base', '5.00') ?>"
                                required
                            >
                            <span class="input-group-text">€</span>
                        </div>
                        <div class="form-text">Montant forfaitaire appliqué à chaque commande.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium" for="livraison_km">Tarif au km (€/km)</label>
                        <div class="input-group">
                            <input
                                type="number"
                                id="livraison_km"
                                name="livraison_km"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= $cfg('livraison_km', '0.50') ?>"
                                required
                            >
                            <span class="input-group-text">€/km</span>
                        </div>
                        <div class="form-text">Multiplié par la distance entre le client et votre adresse.</div>
                    </div>

                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Réduction -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-percent me-2 text-vg"></i>Réduction automatique
            </div>
            <div class="card-body">
                <form method="post" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="hero_sous_titre"  value="<?= $cfg('hero_sous_titre') ?>">
                    <input type="hidden" name="hero_paragraphe"  value="<?= $cfg('hero_paragraphe') ?>">
                    <input type="hidden" name="livraison_base"   value="<?= $cfg('livraison_base',  '5.00') ?>">
                    <input type="hidden" name="livraison_km"     value="<?= $cfg('livraison_km',    '0.50') ?>">

                    <div class="mb-3">
                        <label class="form-label fw-medium" for="reduction_seuil">Seuil de déclenchement (€)</label>
                        <div class="input-group">
                            <input
                                type="number"
                                id="reduction_seuil"
                                name="reduction_seuil"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= $cfg('reduction_seuil', '100.00') ?>"
                                required
                            >
                            <span class="input-group-text">€</span>
                        </div>
                        <div class="form-text">La réduction s'applique dès que la commande atteint ce montant.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium" for="reduction_taux">Taux de réduction (%)</label>
                        <div class="input-group">
                            <input
                                type="number"
                                id="reduction_taux"
                                name="reduction_taux"
                                class="form-control"
                                min="0"
                                max="100"
                                step="1"
                                value="<?= $cfg('reduction_taux', '10') ?>"
                                required
                            >
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Pourcentage déduit du sous-total lorsque le seuil est atteint.</div>
                    </div>

                    <div class="p-3 rounded mb-3" style="background:var(--vg-creme);">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1 text-vg"></i>
                            Exemple : avec un seuil à <strong id="prev-seuil"><?= $cfg('reduction_seuil', '100.00') ?> €</strong>
                            et un taux de <strong id="prev-taux"><?= $cfg('reduction_taux', '10') ?>%</strong>,
                            une commande de 120 € bénéficiera d'une réduction de
                            <strong id="prev-montant"><?= number_format((float)($config['reduction_seuil'] ?? 100) * (float)($config['reduction_taux'] ?? 10) / 100, 2) ?> €</strong>.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script nonce="<?= $cspNonce ?>">
(function () {
    var seuil = document.getElementById('reduction_seuil');
    var taux  = document.getElementById('reduction_taux');
    var pSeuil   = document.getElementById('prev-seuil');
    var pTaux    = document.getElementById('prev-taux');
    var pMontant = document.getElementById('prev-montant');
    if (!seuil || !taux || !pSeuil) return;
    function update() {
        var s = parseFloat(seuil.value) || 0;
        var t = parseFloat(taux.value)  || 0;
        pSeuil.textContent   = s.toFixed(2) + ' €';
        pTaux.textContent    = t.toFixed(0) + '%';
        pMontant.textContent = (s * t / 100).toFixed(2) + ' €';
    }
    seuil.addEventListener('input', update);
    taux.addEventListener('input', update);
}());
</script>
