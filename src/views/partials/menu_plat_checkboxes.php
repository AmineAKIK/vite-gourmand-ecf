<?php if (!empty($platsByCategorie)): ?>
<fieldset class="col-12">
    <legend class="form-label fw-semibold fs-6">Plats composant ce menu</legend>
    <div class="row g-2">
        <?php foreach ($platsByCategorie as $categorie => $platsGroupe): ?>
        <div class="col-md-4">
            <p class="fw-semibold small text-vg mb-1"><?= sanitize($categorie) ?></p>
            <?php foreach ($platsGroupe as $plat): ?>
            <?php $inputId = ($idPrefix ?? 'plat') . '-' . (int)$plat['plat_id']; ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                    name="plats[]"
                    value="<?= (int)$plat['plat_id'] ?>"
                    id="<?= sanitize($inputId) ?>"
                    <?= in_array((int)$plat['plat_id'], $selectedPlatIds ?? [], true) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="<?= sanitize($inputId) ?>">
                    <?= sanitize($plat['titre']) ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</fieldset>
<?php endif; ?>
