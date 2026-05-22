<?php
$dashboardUrl = roleHomePath();
$dashboardLabel = roleHomeLabel();
?>
<a href="<?= sanitize($dashboardUrl) ?>" class="btn btn-outline-secondary btn-sm" aria-label="Retour au tableau de bord - <?= sanitize($dashboardLabel) ?>">
    <i class="bi bi-arrow-left me-1"></i>Tableau de bord
</a>
