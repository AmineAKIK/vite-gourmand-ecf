<?php if (!empty($allergenes)): ?>
<fieldset class="mb-3">
    <legend class="form-label fs-6">Allergènes</legend>
    <div class="row g-1">
        <?php foreach ($allergenes as $allergene): ?>
        <?php $inputId = ($idPrefix ?? 'allergene') . '-' . (int)$allergene['allergene_id']; ?>
        <div class="col-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                    name="allergenes[]"
                    value="<?= (int)$allergene['allergene_id'] ?>"
                    id="<?= sanitize($inputId) ?>"
                    <?= in_array((int)$allergene['allergene_id'], $selectedAllergeneIds ?? [], true) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="<?= sanitize($inputId) ?>">
                    <?= sanitize($allergene['libelle']) ?>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</fieldset>
<?php endif; ?>
