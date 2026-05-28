<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? buildPageTitle()) ?></title>
    <?php $siteLogo    = \App\Config\SiteConfig::logoUrl(); ?>
    <?php $siteFavicon = \App\Models\SiteImageModel::get('favicon'); ?>
    <?php if ($siteFavicon): ?>
        <link rel="icon" type="image/png" href="<?= sanitize($siteFavicon) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="/favicon.png">
    <?php endif; ?>
    <?php $cspNonce = $GLOBALS['csp_nonce'] ?? ''; ?>
    <style nonce="<?= $cspNonce ?>">
        :root {
            --vg-bordeaux: <?= sanitize(siteColor('couleur_principale')) ?>;
            --vg-or:       <?= sanitize(siteColor('couleur_secondaire')) ?>;
            --vg-creme:    <?= sanitize(siteColor('couleur_fond')) ?>;
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" id="fonts-preload" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap"></noscript>
    <script nonce="<?= $cspNonce ?>">(function(){var l=document.getElementById('fonts-preload');if(l){l.onload=function(){this.rel='stylesheet';};}}());</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="/css/style.css?v=20260526-02" nonce="<?= $cspNonce ?>">
</head>
<body class="workspace-body">

<a href="#workspace-content" class="skip-link visually-hidden-focusable">Aller au contenu</a>

<!-- FLASH MESSAGES (hors sidebar) -->
<?php
// Bandeau plan SaaS — visible uniquement pour l'admin, seulement si plan non-premium
if (hasRole(ROLE_ADMIN)) {
    try {
        $__plan      = \App\Config\PlanConfig::current();
        $__planLabel = \App\Config\PlanConfig::label();
        $__maxCmd    = \App\Config\PlanConfig::maxCommandesMois();
        $__maxEmp    = \App\Config\PlanConfig::maxEmployes();
        $__cmdMonth  = 0;
        if ($__maxCmd > 0) {
            $row = db()->fetchOne(
                "SELECT COUNT(*) AS n FROM commande WHERE date_commande >= DATE_FORMAT(NOW(), '%Y-%m-01') AND statut != 'annulee'",
                []
            );
            $__cmdMonth = (int)($row['n'] ?? 0);
        }
        $__showPlanBanner = ($__plan !== 'premium');
        $__quotaWarning   = $__maxCmd > 0 && $__cmdMonth >= (int)($__maxCmd * 0.8);
    } catch (\Throwable) {
        $__showPlanBanner = false;
        $__quotaWarning   = false;
    }
}
?>
<?php if (!empty($__showPlanBanner) && hasRole(ROLE_ADMIN)): ?>
<div class="workspace-flash workspace-plan-banner alert alert-info m-0 rounded-0 border-0 d-flex align-items-center justify-content-between py-2" role="status">
    <div class="container-fluid d-flex align-items-center gap-3 flex-wrap">
        <span>
            <i class="bi bi-award me-1"></i>
            Plan actuel : <strong><?= sanitize($__planLabel) ?></strong>
            <?php if ($__maxCmd > 0): ?>
            — <?= $__cmdMonth ?>/<?= $__maxCmd ?> commandes ce mois
            <?php endif; ?>
            <?php if ($__maxEmp > 0): ?>
            — max <?= $__maxEmp ?> employé<?= $__maxEmp > 1 ? 's' : '' ?>
            <?php endif; ?>
        </span>
        <a href="/admin/parametres#plan" class="btn btn-sm btn-outline-primary ms-auto text-nowrap">
            <i class="bi bi-arrow-up-circle me-1"></i>Changer de plan
        </a>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($__quotaWarning) && hasRole(ROLE_ADMIN)): ?>
<div class="workspace-flash alert alert-warning m-0 rounded-0 border-0" role="alert">
    <div class="container-fluid"><i class="bi bi-exclamation-triangle me-2"></i>
    Quota commandes bientôt atteint : <?= $__cmdMonth ?>/<?= $__maxCmd ?> ce mois.
    <a href="/admin/parametres#plan" class="alert-link">Passer au plan supérieur</a> pour éviter les blocages.
    </div>
</div>
<?php endif; ?>
<?php if ($msg = getFlash('success')): ?>
<div class="workspace-flash alert alert-success alert-dismissible fade show m-0 rounded-0 border-0" role="alert" aria-live="polite">
    <div class="container-fluid"><i class="bi bi-check-circle me-2"></i><?= sanitize($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
</div>
<?php endif; ?>
<?php if ($msg = getFlash('error')): ?>
<div class="workspace-flash alert alert-danger alert-dismissible fade show m-0 rounded-0 border-0" role="alert" aria-live="polite">
    <div class="container-fluid"><i class="bi bi-exclamation-triangle me-2"></i><?= sanitize($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
</div>
<?php endif; ?>

<div class="workspace-layout">

    <!-- SIDEBAR -->
    <aside class="workspace-sidebar" id="workspaceSidebar" role="navigation" aria-label="Navigation back-office">

        <!-- Logo / brand -->
        <div class="workspace-brand">
            <a href="/" class="workspace-brand-link" aria-label="Retour au site">
                <?php if ($siteLogo): ?>
                    <img src="<?= sanitize($siteLogo) ?>" alt="<?= sanitize(siteName()) ?>" class="workspace-brand-logo">
                <?php else: ?>
                    <span class="workspace-brand-name"><?= sanitize(siteName()) ?></span>
                <?php endif; ?>
            </a>
            <span class="workspace-role-badge">
                <?= hasRole(ROLE_ADMIN) ? 'Admin' : 'Employé' ?>
            </span>
        </div>

        <!-- Nav items -->
        <nav class="workspace-nav-list">
            <?php foreach (workspaceNavItems() as $item):
                if (!empty($item['separator'])): ?>
                    <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1.25rem;">
                <?php continue; endif;
                $isActive = !empty($item['exact'])
                    ? ($_SERVER['REQUEST_URI'] === $item['href'])
                    : routeIsActive($item['match'] ?? $item['href']);
            ?>
            <a href="<?= sanitize($item['href']) ?>"
               class="workspace-nav-item <?= $isActive ? 'active' : '' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= sanitize($item['icon']) ?> workspace-nav-icon"></i>
                <span><?= sanitize($item['label']) ?></span>
            </a>
            <?php endforeach; ?>

            <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1.25rem;">

            <a href="/" class="workspace-nav-item">
                <span>Retour au site</span>
            </a>
            <a href="/deconnexion" class="workspace-nav-item workspace-nav-item--danger">
                <span>Déconnexion</span>
            </a>
        </nav>

        <!-- Footer sidebar -->
        <div class="workspace-sidebar-footer">
            <form class="workspace-search-form" method="GET" action="/employe/recherche" role="search">
                <div class="input-group input-group-sm">
                    <input class="form-control workspace-search-input" type="search" name="q"
                           placeholder="Rechercher…" aria-label="Recherche globale"
                           value="<?= sanitize($_GET['q'] ?? '') ?>" minlength="2">
                    <button class="btn workspace-search-btn" type="submit" aria-label="Rechercher">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            <a href="/employe/notifications" class="workspace-nav-item workspace-notif-link" id="sidebar-notif-link"
               aria-label="Notifications" <?= routeIsActive('/employe/notifications') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-bell workspace-nav-icon"></i>
                <span>Notifications</span>
                <span class="badge bg-warning text-dark ms-auto workspace-notif-badge d-none" id="notif-badge" aria-live="polite"></span>
            </a>
            <div class="workspace-user">
                <i class="bi bi-person-circle workspace-nav-icon"></i>
                <span><?= sanitize(currentUser()['prenom'] ?? '') ?> <?= sanitize(currentUser()['nom'] ?? '') ?></span>
            </div>
            <div class="workspace-vendor">
                <a href="<?= APP_VENDOR_URL ?>" target="_blank" rel="noopener"><?= APP_NAME ?> v<?= APP_VERSION ?></a>
            </div>
        </div>

    </aside>

    <!-- CONTENU PRINCIPAL -->
    <main class="workspace-main" id="workspace-content" tabindex="-1">

        <!-- Navbar mobile (< lg) -->
        <nav class="d-lg-none navbar navbar-expand-lg navbar-dark bg-vg sticky-top" aria-label="Navigation back-office mobile">
            <div class="container">
                <?php if ($siteLogo): ?>
                    <img src="<?= sanitize($siteLogo) ?>" alt="<?= sanitize(siteName()) ?>" class="site-brand-logo">
                <?php else: ?>
                    <span class="navbar-brand fw-bold mb-0"><?= sanitize(siteName()) ?></span>
                <?php endif; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#workspaceMobileNav" aria-controls="workspaceMobileNav" aria-expanded="false" aria-label="Ouvrir le menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="workspaceMobileNav">
                    <form class="d-flex mt-2 mb-1 px-2" method="GET" action="/employe/recherche" role="search">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" name="q" placeholder="Rechercher…"
                                   aria-label="Recherche globale"
                                   value="<?= sanitize($_GET['q'] ?? '') ?>"
                                   minlength="2">
                            <button class="btn btn-outline-light" type="submit" aria-label="Lancer la recherche">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                    <ul class="navbar-nav w-100 mt-1">
                        <?php foreach (workspaceNavItems() as $item):
                            if (!empty($item['separator'])): ?>
                                <li><hr style="border-color:rgba(255,255,255,.2);margin:.25rem 0;"></li>
                            <?php continue; endif;
                            $isActive = !empty($item['exact'])
                                ? ($_SERVER['REQUEST_URI'] === $item['href'])
                                : routeIsActive($item['match'] ?? $item['href']);
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= sanitize($item['href']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                                <i class="bi <?= sanitize($item['icon']) ?> me-2"></i><?= sanitize($item['label']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><hr style="border-color:rgba(255,255,255,.2);margin:.25rem 0;"></li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employe/notifications">
                                <i class="bi bi-bell me-2"></i>Notifications
                                <span class="badge bg-warning text-dark ms-1 workspace-notif-badge-mobile d-none" id="notif-badge-mobile"></span>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="/">Retour au site</a></li>
                        <li class="nav-item"><a class="nav-link" href="/deconnexion">Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="workspace-content-inner">
            <?= $content ?? '' ?>
        </div>

    </main>

</div><!-- /workspace-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>"></script>
<script src="/js/app.js?v=20260526-03" nonce="<?= $cspNonce ?>"></script>
<script nonce="<?= $cspNonce ?>">
(function () {
    var badge       = document.getElementById('notif-badge');
    var badgeMobile = document.getElementById('notif-badge-mobile');

    function updateBadge(count) {
        [badge, badgeMobile].forEach(function (el) {
            if (!el) return;
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : count;
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });
    }

    function fetchCount() {
        fetch('/employe/notifications/count', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (data) updateBadge(data.count); })
            .catch(function () {});
    }

    fetchCount();
    setInterval(fetchCount, 60000);
}());
</script>
</body>
</html>
