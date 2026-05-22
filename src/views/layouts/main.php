<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Vite & Gourmand' ?></title>
    <?php foreach (($preloadImages ?? []) as $image): ?>
        <link rel="preload" as="image" href="<?= sanitize($image) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/style.css?v=20260522-12">
</head>
<body>

<a href="#main-content" class="skip-link visually-hidden-focusable">
    Aller au contenu principal
</a>

<!-- NAVBAR -->
<?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
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
                    <a class="nav-link <?= $currentPath === '/' ? 'active' : '' ?>" href="/" <?= $currentPath === '/' ? 'aria-current="page"' : '' ?>>Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with($currentPath, '/menus') ? 'active' : '' ?>" href="/menus" <?= str_starts_with($currentPath, '/menus') ? 'aria-current="page"' : '' ?>>Tous les menus</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/contact' ? 'active' : '' ?>" href="/contact" <?= $currentPath === '/contact' ? 'aria-current="page"' : '' ?>>Contact</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isAuth()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <?= sanitize(currentUser()['prenom']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (hasRole('administrateur')): ?>
                                <li><a class="dropdown-item" href="/admin">Espace administrateur</a></li>
                            <?php elseif (hasRole('employe')): ?>
                                <li><a class="dropdown-item" href="/employe">Espace employé</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="/mon-compte">Mon compte</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/deconnexion">Déconnexion</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPath === '/connexion' ? 'active' : '' ?>" href="/connexion" <?= $currentPath === '/connexion' ? 'aria-current="page"' : '' ?>>Connexion</a>
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
                <?php
                $db = Database::getConnection();
                $horaires = $db->query("SELECT * FROM horaire ORDER BY horaire_id")->fetchAll();
                ?>
                <ul class="footer-hours list-unstyled mb-0">
                    <?php foreach ($horaires as $h): ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/app.js"></script>
</body>
</html>
