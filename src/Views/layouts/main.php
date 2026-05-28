<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? buildPageTitle()) ?></title>
    <?php $siteLogo   = \App\Config\SiteConfig::logoUrl(); ?>
    <?php $siteFavicon = \App\Models\SiteImageModel::get('favicon'); ?>
    <?php $siteOgImage = \App\Models\SiteImageModel::get('og_image'); ?>
    <?php if ($siteFavicon): ?>
        <link rel="icon" type="image/png" href="<?= sanitize($siteFavicon) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="/favicon.png">
    <?php endif; ?>
    <meta property="og:title" content="<?= sanitize(siteName()) ?> — <?= sanitize(siteSlogan()) ?>">
    <meta property="og:description" content="<?= sanitize(siteSlogan()) ?>. Découvrez nos menus et commandez en ligne pour tous vos événements.">
    <meta property="og:image" content="<?= sanitize($siteOgImage ?: '/og/og-default.png') ?>">
    <meta property="og:type" content="website">
    <?php foreach (($preloadImages ?? []) as $image): ?>
        <link rel="preload" as="image" href="<?= sanitize($image) ?>">
    <?php endforeach; ?>
    <?php $cspNonce = $GLOBALS['csp_nonce'] ?? ''; ?>
    <style nonce="<?= $cspNonce ?>">
        :root {
            --vg-bordeaux: <?= sanitize(siteColor('couleur_principale')) ?>;
            --vg-or:       <?= sanitize(siteColor('couleur_secondaire')) ?>;
            --vg-creme:    <?= sanitize(siteColor('couleur_fond')) ?>;
        }
    </style>
    <!-- Preconnect Google Fonts pour réduire la latence -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" id="fonts-preload" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap"></noscript>
    <script nonce="<?= $cspNonce ?>">(function(){var l=document.getElementById('fonts-preload');if(l){l.onload=function(){this.rel='stylesheet';};}}());</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="/css/style.css?v=20260526-02" nonce="<?= $cspNonce ?>">
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
<nav class="navbar navbar-expand-xl navbar-dark bg-vg sticky-top site-navbar" role="navigation" aria-label="Navigation principale">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/" aria-label="Retour à l'accueil <?= sanitize(siteName()) ?>">
            <?php if ($siteLogo): ?>
                <img src="<?= sanitize($siteLogo) ?>" alt="<?= sanitize(siteName()) ?>" class="site-brand-logo">
            <?php else: ?>
                <span class="site-brand-name"><?= sanitize(siteName()) ?></span>
                <span class="site-brand-kicker d-none d-xl-block"><?= sanitize(siteSlogan()) ?></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Ouvrir le menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse text-start" id="navMain">
            <ul class="navbar-nav main-nav-list me-auto mb-0">
                <li class="nav-section-title d-xl-none">Navigation</li>
                <li class="nav-item">
                    <a class="nav-link <?= routeIsActive('/') ? 'active' : '' ?>" href="/" <?= routeIsActive('/') ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-house-door nav-link-icon d-xl-none" aria-hidden="true"></i>
                        <span>Accueil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= routeIsActive('/menus*') ? 'active' : '' ?>" href="/menus" <?= routeIsActive('/menus*') ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-journal-richtext nav-link-icon d-xl-none" aria-hidden="true"></i>
                        <span>Tous les menus</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= routeIsActive('/contact') ? 'active' : '' ?>" href="/contact" <?= routeIsActive('/contact') ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-chat-left-text nav-link-icon d-xl-none" aria-hidden="true"></i>
                        <span>Contact</span>
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav account-nav-list ms-auto align-items-xl-center">
                <?php if (isAuth()): ?>
                    <li class="nav-section-title d-xl-none">Compte</li>
                    <li class="nav-item me-1">
                        <?php $panierCount = count($_SESSION['panier'] ?? []); ?>
                        <a class="nav-link nav-link-cart" href="/panier" aria-label="Votre panier (<?= $panierCount ?> article<?= $panierCount > 1 ? 's' : '' ?>)">
                            <span class="cart-icon-wrap">
                                <i class="bi bi-cart3 nav-link-icon" aria-hidden="true"></i>
                                <?php if ($panierCount > 0): ?>
                                    <span class="cart-badge"><?= $panierCount ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="d-xl-none">Votre panier<?= $panierCount > 0 ? ' (' . $panierCount . ')' : '' ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php
                            $navHref  = hasRole(ROLE_ADMIN) || hasRole(ROLE_EMPLOYE) ? roleHomePath() : '/mon-compte';
                            $navActive = hasRole(ROLE_ADMIN) || hasRole(ROLE_EMPLOYE) ? $workspaceActive : routeIsActive('/mon-compte');
                        ?>
                        <a class="nav-link <?= $navActive ? 'active' : '' ?>" href="<?= sanitize($navHref) ?>" <?= $navActive ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-person-workspace nav-link-icon d-xl-none" aria-hidden="true"></i>
                            <span><?= sanitize(roleHomeLabel()) ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-logout" href="/deconnexion">
                            <i class="bi bi-box-arrow-right nav-link-icon d-xl-none" aria-hidden="true"></i>
                            <span>Déconnexion</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-section-title d-xl-none">Compte</li>
                    <li class="nav-item">
                        <a class="nav-link <?= routeIsActive('/connexion') ? 'active' : '' ?>" href="/connexion" <?= routeIsActive('/connexion') ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-person-circle nav-link-icon d-xl-none" aria-hidden="true"></i>
                            <span>Connexion</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- FLASH MESSAGES -->
<div class="container mt-3">
    <?php if ($msg = getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" aria-live="polite">
            <i class="bi bi-check-circle me-2"></i><?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg = getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="polite">
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
        <div class="text-center mt-3 pt-3 border-top border-secondary">
            <small class="text-muted" style="font-size:.7rem;opacity:.6;">
                Propulsé par <a href="<?= APP_VENDOR_URL ?>" target="_blank" rel="noopener" class="text-muted"><?= APP_NAME ?></a> v<?= APP_VERSION ?>
            </small>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>"></script>
<script src="/js/app.js?v=20260526-03" nonce="<?= $cspNonce ?>"></script>
</body>
</html>
