<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Vite & Gourmand' ?></title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <?php $cspNonce = $GLOBALS['csp_nonce'] ?? ''; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" id="fonts-preload" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap"></noscript>
    <script nonce="<?= $cspNonce ?>">(function(){var l=document.getElementById('fonts-preload');if(l){l.onload=function(){this.rel='stylesheet';};}}());</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" nonce="<?= $cspNonce ?>">
    <link rel="stylesheet" href="/css/style.css?v=20260523-47" nonce="<?= $cspNonce ?>">
</head>
<body class="workspace-body">

<a href="#workspace-content" class="skip-link visually-hidden-focusable">Aller au contenu</a>

<!-- FLASH MESSAGES (hors sidebar) -->
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
                <span class="workspace-brand-name">Vite &amp; Gourmand</span>
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
                <i class="bi bi-box-arrow-up-right workspace-nav-icon"></i>
                <span>Retour au site</span>
            </a>
            <a href="/deconnexion" class="workspace-nav-item workspace-nav-item--danger">
                <i class="bi bi-box-arrow-right workspace-nav-icon"></i>
                <span>Déconnexion</span>
            </a>
        </nav>

        <!-- Footer sidebar -->
        <div class="workspace-sidebar-footer">
            <div class="workspace-user">
                <i class="bi bi-person-circle workspace-nav-icon"></i>
                <span><?= sanitize(currentUser()['prenom'] ?? '') ?> <?= sanitize(currentUser()['nom'] ?? '') ?></span>
            </div>
        </div>

    </aside>

    <!-- CONTENU PRINCIPAL -->
    <main class="workspace-main" id="workspace-content" tabindex="-1">

        <!-- Navbar mobile (< lg) -->
        <nav class="d-lg-none navbar navbar-expand-lg navbar-dark bg-vg sticky-top" aria-label="Navigation back-office mobile">
            <div class="container">
                <span class="navbar-brand fw-bold mb-0">Vite &amp; Gourmand</span>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#workspaceMobileNav" aria-controls="workspaceMobileNav" aria-expanded="false" aria-label="Ouvrir le menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="workspaceMobileNav">
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
                        <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-box-arrow-up-right me-2"></i>Retour au site</a></li>
                        <li class="nav-item"><a class="nav-link" href="/deconnexion"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
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
<script src="/js/app.js" nonce="<?= $cspNonce ?>"></script>
</body>
</html>
