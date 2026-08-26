<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? buildPageTitle()) ?></title>
    <?php
    $siteLogo = \App\Config\SiteConfig::logoUrl();
    $siteFavicon = \App\Models\SiteImageModel::get(\App\Domain\BrandAsset::FAVICON);
    $cspNonce = $GLOBALS['csp_nonce'] ?? '';
    ?>
    <?php if ($siteFavicon): ?><link rel="icon" href="<?= sanitize($siteFavicon) ?>"><?php endif; ?>
    <style nonce="<?= sanitize($cspNonce) ?>"><?= \App\Config\DesignTokens::inlineCss() ?></style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?= sanitize($cspNonce) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" nonce="<?= sanitize($cspNonce) ?>">
    <link rel="stylesheet" href="/css/style.css?v=20260826-01" nonce="<?= sanitize($cspNonce) ?>">
</head>
<body class="workspace-body">
<a href="#workspace-content" class="skip-link visually-hidden-focusable">Aller au contenu</a>

<?php if ($msg = getFlash('success')): ?>
    <div class="workspace-flash alert alert-success alert-dismissible fade show m-0 rounded-0 border-0" role="alert" aria-live="polite">
        <div class="container-fluid"><i class="bi bi-check-circle me-2"></i><?= sanitize($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
    </div>
<?php endif; ?>
<?php if ($msg = getFlash('error')): ?>
    <div class="workspace-flash alert alert-danger alert-dismissible fade show m-0 rounded-0 border-0" role="alert" aria-live="polite">
        <div class="container-fluid"><i class="bi bi-exclamation-triangle me-2"></i><?= sanitize($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
    </div>
<?php endif; ?>

<div class="workspace-shell">
    <aside class="workspace-sidebar d-none d-lg-flex flex-column" id="workspaceSidebar" aria-label="Navigation back-office">
        <div class="p-3 border-bottom border-subtle">
            <a href="/" class="d-flex align-items-center gap-2 text-decoration-none" aria-label="Retour au site">
                <?php if ($siteLogo): ?>
                    <img src="<?= sanitize($siteLogo) ?>" alt="<?= sanitize(siteName()) ?>" class="site-brand-logo">
                <?php else: ?>
                    <strong class="text-brand"><?= sanitize(siteName()) ?></strong>
                <?php endif; ?>
            </a>
            <span class="badge text-bg-light mt-2"><?= hasRole(ROLE_ADMIN) ? 'Administrateur' : 'Employé' ?></span>
        </div>

        <nav class="nav nav-pills flex-column gap-1 p-3" aria-label="Sections du back-office">
            <?php foreach (workspaceNavItems() as $item): ?>
                <?php if (!empty($item['separator'])): ?>
                    <hr class="workspace-nav-separator my-2">
                    <?php continue; ?>
                <?php endif; ?>
                <?php
                $isActive = !empty($item['exact'])
                    ? ($_SERVER['REQUEST_URI'] === $item['href'])
                    : routeIsActive($item['match'] ?? $item['href']);
                ?>
                <a href="<?= sanitize($item['href']) ?>" class="nav-link d-flex align-items-center gap-2 <?= $isActive ? 'active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= sanitize($item['icon']) ?>" aria-hidden="true"></i><span><?= sanitize($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
            <hr class="workspace-nav-separator my-2">
            <a href="/" class="nav-link d-flex align-items-center gap-2"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>Retour au site</span></a>
            <form method="POST" action="/deconnexion" class="m-0">
                <?= csrfField() ?>
                <button type="submit" class="nav-link d-flex align-items-center gap-2 border-0 bg-transparent w-100 text-start text-danger">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Déconnexion</span>
                </button>
            </form>
        </nav>

        <div class="mt-auto p-3 border-top border-subtle">
            <form method="GET" action="/employe/recherche" role="search" class="mb-2">
                <div class="input-group input-group-sm">
                    <input class="form-control" type="search" name="q" placeholder="Rechercher…" aria-label="Recherche globale" value="<?= sanitize($_GET['q'] ?? '') ?>" minlength="2">
                    <button class="btn btn-outline-secondary" type="submit" aria-label="Rechercher"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <a href="/employe/notifications" class="nav-link d-flex align-items-center gap-2 px-0" <?= routeIsActive('/employe/notifications') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-bell" aria-hidden="true"></i><span>Notifications</span><span class="badge text-bg-warning ms-auto d-none" id="notif-badge" aria-live="polite"></span>
            </a>
            <div class="small text-muted mt-3"><i class="bi bi-person-circle me-1"></i><?= sanitize(currentUser()['prenom'] ?? '') ?> <?= sanitize(currentUser()['nom'] ?? '') ?></div>
            <div class="small text-muted mt-2"><a href="<?= APP_VENDOR_URL ?>" target="_blank" rel="noopener"><?= APP_NAME ?> v<?= APP_VERSION ?></a></div>
        </div>
    </aside>

    <main class="workspace-main" id="workspace-content" tabindex="-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-brand sticky-top d-lg-none" aria-label="Navigation back-office mobile">
            <div class="container-fluid">
                <a class="navbar-brand" href="/">
                    <?php if ($siteLogo): ?><img src="<?= sanitize($siteLogo) ?>" alt="<?= sanitize(siteName()) ?>" class="site-brand-logo"><?php else: ?><?= sanitize(siteName()) ?><?php endif; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#workspaceMobileNav" aria-controls="workspaceMobileNav" aria-expanded="false" aria-label="Ouvrir le menu"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="workspaceMobileNav">
                    <form class="d-flex my-2" method="GET" action="/employe/recherche" role="search">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" name="q" placeholder="Rechercher…" aria-label="Recherche globale" value="<?= sanitize($_GET['q'] ?? '') ?>" minlength="2">
                            <button class="btn btn-outline-light" type="submit" aria-label="Lancer la recherche"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                    <ul class="navbar-nav">
                        <?php foreach (workspaceNavItems() as $item): ?>
                            <?php if (!empty($item['separator'])): ?><li><hr class="border-light opacity-25"></li><?php continue; endif; ?>
                            <?php
                            $isActive = !empty($item['exact'])
                                ? ($_SERVER['REQUEST_URI'] === $item['href'])
                                : routeIsActive($item['match'] ?? $item['href']);
                            ?>
                            <li class="nav-item"><a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= sanitize($item['href']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><i class="bi <?= sanitize($item['icon']) ?> me-2"></i><?= sanitize($item['label']) ?></a></li>
                        <?php endforeach; ?>
                        <li><hr class="border-light opacity-25"></li>
                        <li class="nav-item"><a class="nav-link" href="/employe/notifications"><i class="bi bi-bell me-2"></i>Notifications <span class="badge text-bg-warning ms-1 d-none" id="notif-badge-mobile"></span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/">Retour au site</a></li>
                        <li class="nav-item">
                            <form method="POST" action="/deconnexion" class="m-0"><?= csrfField() ?><button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">Déconnexion</button></form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="workspace-content p-3 p-lg-4"><?= $content ?? '' ?></div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= sanitize($cspNonce) ?>"></script>
<script src="/js/app.js?v=20260826-01" nonce="<?= sanitize($cspNonce) ?>"></script>
<script nonce="<?= sanitize($cspNonce) ?>">
(function () {
    var badges = [document.getElementById('notif-badge'), document.getElementById('notif-badge-mobile')];
    function updateBadge(count) {
        badges.forEach(function (badge) {
            if (!badge) return;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        });
    }
    function fetchCount() {
        fetch('/employe/notifications/count', {credentials: 'same-origin'})
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) { if (data) updateBadge(data.count); })
            .catch(function () {});
    }
    fetchCount();
    setInterval(fetchCount, 60000);
}());
</script>
</body>
</html>
