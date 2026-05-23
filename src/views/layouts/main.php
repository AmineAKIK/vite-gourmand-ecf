<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Vite & Gourmand' ?></title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <meta property="og:title" content="Vite & Gourmand — Traiteur à Bordeaux">
    <meta property="og:description" content="Traiteur bordelais depuis 25 ans. Découvrez nos menus et commandez en ligne pour tous vos événements.">
    <meta property="og:image" content="/og/ogvg.png">
    <meta property="og:type" content="website">
    <?php foreach (($preloadImages ?? []) as $image): ?>
        <link rel="preload" as="image" href="<?= sanitize($image) ?>">
    <?php endforeach; ?>
    <?php $cspNonce = $GLOBALS['csp_nonce'] ?? ''; ?>
    <!-- Preconnect Google Fonts pour réduire la latence -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" id="fonts-preload" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap"></noscript>
    <script nonce="<?= $cspNonce ?>">(function(){var l=document.getElementById('fonts-preload');if(l){l.onload=function(){this.rel='stylesheet';};}}());</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="/css/style.css?v=20260523-27" nonce="<?= $cspNonce ?>">
</head>
<body>

<a href="#main-content" class="skip-link visually-hidden-focusable">
    Aller au contenu principal
</a>

<!-- NAVBAR -->
<?php
$workspaceActive = isAuth() && roleWorkspaceIsActive();
$roleHomeIsCurrent = isAuth() && routeIsActive(roleHomePath());
?>
<nav class="navbar navbar-expand-md navbar-dark bg-vg sticky-top" role="navigation" aria-label="Navigation principale">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/" aria-label="Retour à l'accueil Vite et Gourmand">
            Vite & Gourmand
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Ouvrir le menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-0">
                <li class="nav-item">
                    <a class="nav-link <?= routeIsActive('/') ? 'active' : '' ?>" href="/" <?= routeIsActive('/') ? 'aria-current="page"' : '' ?>>Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= routeIsActive('/menus*') ? 'active' : '' ?>" href="/menus" <?= routeIsActive('/menus*') ? 'aria-current="page"' : '' ?>>Tous les menus</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= routeIsActive('/contact') ? 'active' : '' ?>" href="/contact" <?= routeIsActive('/contact') ? 'aria-current="page"' : '' ?>>Contact</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isAuth()): ?>
                    <li class="nav-item dropdown">
                        <a
                            class="nav-link dropdown-toggle <?= $workspaceActive ? 'active' : '' ?>"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            <?= $roleHomeIsCurrent ? 'aria-current="page"' : '' ?>
                        >
                            <?= sanitize(currentUser()['prenom']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item <?= $roleHomeIsCurrent ? 'active' : '' ?>" href="<?= sanitize(roleHomePath()) ?>">
                                    <?= sanitize(roleHomeLabel()) ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/deconnexion">Déconnexion</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= routeIsActive('/connexion') ? 'active' : '' ?>" href="/connexion" <?= routeIsActive('/connexion') ? 'aria-current="page"' : '' ?>>Connexion</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- FLASH MESSAGES -->
<div class="container mt-3" role="alert" aria-live="polite">
    <?php if ($msg = getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= sanitize($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg = getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= sanitize($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>
</div>

<!-- CONTENU -->
<main id="main-content" tabindex="-1">
<?= $content ?? '' ?>
</main>

<!-- FOOTER -->
<footer class="site-footer bg-dark text-light py-4 mt-5" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <section class="footer-section" aria-labelledby="footer-horaires">
                <h2 id="footer-horaires" class="footer-title">Horaires d'ouverture</h2>
                <ul class="footer-hours list-unstyled mb-0">
                    <?php foreach (($siteHoraires ?? []) as $h): ?>
                        <li>
                            <strong><?= sanitize($h['jour']) ?> :</strong>
                            <?= $h['heure_ouverture'] === 'Fermé' ? '<span class="text-danger">Fermé</span>' : sanitize($h['heure_ouverture']) . ' - ' . sanitize($h['heure_fermeture']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="footer-section footer-links-section" aria-labelledby="footer-infos">
                <h2 id="footer-infos" class="footer-title">Informations</h2>
                <ul class="footer-links list-unstyled mb-0">
                    <li><a href="/mentions-legales">Mentions légales</a></li>
                    <li><a href="/cgv">Conditions générales de vente</a></li>
                </ul>
            </section>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>"></script>
<script src="/js/app.js" nonce="<?= $cspNonce ?>"></script>
</body>
</html>
