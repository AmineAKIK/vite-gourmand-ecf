<?php
// src/helpers.php

function requireAuth(): void {
    if (empty($_SESSION['user'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /connexion'); exit;
    }
}

function requireRole(array $roles): void {
    requireAuth();
    if (!in_array($_SESSION['user']['role'] ?? '', $roles)) {
        http_response_code(403);
        die(view('pages/403'));
    }
}

function isAuth(): bool {
    return !empty($_SESSION['user']);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function hasRole(string $role): bool {
    return ($_SESSION['user']['role'] ?? '') === $role;
}

function isEmployeOrAdmin(): bool {
    return in_array($_SESSION['user']['role'] ?? '', [ROLE_EMPLOYE, ROLE_ADMIN]);
}

function view(string $template, array $data = []): void {
    if (!array_key_exists('siteHoraires', $data) && class_exists('HoraireModel')) {
        $data['siteHoraires'] = HoraireModel::getAll();
    }
    extract($data);
    $file = __DIR__ . '/views/' . $template . '.php';
    if (!file_exists($file)) { die("Vue introuvable : $template"); }
    ob_start();
    require $file;
    $content = ob_get_clean();
    require __DIR__ . '/views/layouts/main.php';
}

function partial(string $template, array $data = []): void {
    extract($data);
    $file = __DIR__ . '/views/' . $template . '.php';
    if (!file_exists($file)) { die("Partial introuvable : $template"); }
    require $file;
}

function redirect(string $url): void {
    header("Location: $url"); exit;
}

function currentPath(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') ?: '/';
}

function routeIsActive(string|array $patterns): bool {
    $current = currentPath();
    foreach ((array)$patterns as $pattern) {
        $pattern = rtrim($pattern, '/') ?: '/';
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim(substr($pattern, 0, -1), '/') ?: '/';
            if ($current === $prefix || str_starts_with($current, $prefix . '/')) {
                return true;
            }
            continue;
        }
        if ($current === $pattern) {
            return true;
        }
    }
    return false;
}

function roleHomePath(?string $role = null): string {
    $role = $role ?? ($_SESSION['user']['role'] ?? '');
    return match ($role) {
        ROLE_ADMIN => '/admin',
        ROLE_EMPLOYE => '/employe',
        default => '/mon-compte',
    };
}

function roleHomeLabel(?string $role = null): string {
    $role = $role ?? ($_SESSION['user']['role'] ?? '');
    return match ($role) {
        ROLE_ADMIN => 'Espace administrateur',
        ROLE_EMPLOYE => 'Espace employé',
        default => 'Mon compte',
    };
}

function roleWorkspaceIsActive(?string $role = null): bool {
    $role = $role ?? ($_SESSION['user']['role'] ?? '');
    return match ($role) {
        ROLE_ADMIN => routeIsActive(['/admin*', '/employe*']),
        ROLE_EMPLOYE => routeIsActive('/employe*'),
        default => routeIsActive(['/mon-compte*', '/commande*']),
    };
}

function workspaceNavItems(): array {
    if (hasRole(ROLE_ADMIN)) {
        return [
            ['href' => '/admin/employes', 'label' => 'Gérer les employés', 'icon' => 'bi-people', 'match' => '/admin/employes*'],
            ['href' => '/admin/stats', 'label' => 'Statistiques CA', 'icon' => 'bi-graph-up', 'match' => '/admin/stats*'],
            ['href' => '/employe/commandes', 'label' => 'Toutes les commandes', 'icon' => 'bi-list-check', 'match' => '/employe/commandes*'],
            ['href' => '/employe/menus', 'label' => 'Menus et plats', 'icon' => 'bi-journal-text', 'match' => '/employe/menus*'],
            ['href' => '/employe/avis', 'label' => 'Avis', 'icon' => 'bi-star', 'match' => '/employe/avis*'],
            ['href' => '/employe/horaires', 'label' => 'Horaires', 'icon' => 'bi-clock', 'match' => '/employe/horaires*'],
        ];
    }

    if (hasRole(ROLE_EMPLOYE)) {
        return [
            ['href' => '/employe/commandes', 'label' => 'Commandes', 'icon' => 'bi-list-check', 'match' => '/employe/commandes*'],
            ['href' => '/employe/menus', 'label' => 'Menus', 'icon' => 'bi-journal-text', 'match' => '/employe/menus*'],
            ['href' => '/employe/avis', 'label' => 'Avis', 'icon' => 'bi-star', 'match' => '/employe/avis*'],
            ['href' => '/employe/horaires', 'label' => 'Horaires', 'icon' => 'bi-clock', 'match' => '/employe/horaires*'],
        ];
    }

    return [];
}

function sanitize(?string $val): string {
    return htmlspecialchars(trim($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . sanitize(csrf()) . '">';
}

function verifyCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        view('pages/403');
        exit;
    }
}

function flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function generateNumeroCommande(): string {
    return 'VG-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Ymd');
}

function commandeStatusDefinitions(): array {
    return [
        'en_attente' => [
            'label' => 'En attente',
            'class' => 'statut-en_attente',
            'transitions' => ['accepte', 'annulee'],
        ],
        'accepte' => [
            'label' => 'Accepté',
            'class' => 'statut-accepte',
            'transitions' => ['en_preparation', 'annulee'],
        ],
        'en_preparation' => [
            'label' => 'En préparation',
            'class' => 'statut-en_preparation',
            'transitions' => ['en_cours_livraison', 'annulee'],
        ],
        'en_cours_livraison' => [
            'label' => 'En cours de livraison',
            'class' => 'statut-en_cours_livraison',
            'transitions' => ['livre', 'annulee'],
        ],
        'livre' => [
            'label' => 'Livré',
            'class' => 'statut-livre',
            'transitions' => ['en_attente_materiel', 'terminee'],
        ],
        'en_attente_materiel' => [
            'label' => 'En attente du retour de matériel',
            'class' => 'statut-en_attente_materiel',
            'transitions' => ['terminee', 'annulee'],
        ],
        'terminee' => [
            'label' => 'Terminée',
            'class' => 'statut-terminee',
            'transitions' => [],
        ],
        'annulee' => [
            'label' => 'Annulée',
            'class' => 'statut-annulee',
            'transitions' => [],
        ],
    ];
}

function commandeStatuses(): array {
    return array_keys(commandeStatusDefinitions());
}

function commandeInitialStatus(): string {
    return 'en_attente';
}

function commandeCancelledStatus(): string {
    return 'annulee';
}

function commandeCompletedStatus(): string {
    return 'terminee';
}

function commandeAwaitingMaterialStatus(): string {
    return 'en_attente_materiel';
}

function commandePreparingStatus(): string {
    return 'en_preparation';
}

function commandeDeliveryStatus(): string {
    return 'en_cours_livraison';
}

function commandeStatusIsValid(string $status): bool {
    return array_key_exists($status, commandeStatusDefinitions());
}

function commandeStatusLabel(?string $status): string {
    $status = $status ?? '';
    return commandeStatusDefinitions()[$status]['label'] ?? ucfirst(str_replace('_', ' ', $status));
}

function commandeStatusBadge(?string $status): string {
    return '<span class="badge-statut ' . sanitize(commandeStatusClass($status)) . '">'
        . sanitize(commandeStatusLabel($status))
        . '</span>';
}

function formatDateFr(?string $date, string $fallback = '—'): string {
    if (empty($date)) {
        return $fallback;
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
}

function formatDateTimeFr(?string $date, string $fallback = '—'): string {
    if (empty($date)) {
        return $fallback;
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y à H\hi', $timestamp) : $fallback;
}

function formatPrice(float|int|string|null $amount, int $decimals = 2): string {
    return number_format((float)($amount ?? 0), $decimals, ',', ' ') . ' €';
}

function formatPriceInput(float|int|string|null $amount): string {
    return number_format((float)($amount ?? 0), 2, '.', '');
}

function formatInteger(float|int|string|null $amount): string {
    return number_format((float)($amount ?? 0), 0, ',', ' ');
}

function tomorrowDateInput(): string {
    return (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
}

function personFullName(array $person): string {
    return trim(($person['prenom'] ?? '') . ' ' . ($person['nom'] ?? ''));
}

function passwordPolicyMessage(): string {
    return 'Mot de passe trop faible (10 car. min, 1 maj, 1 min, 1 chiffre, 1 spécial).';
}

function passwordPolicyRules(): array {
    return [
        'Au moins 10 caractères',
        'Au moins une majuscule (A-Z)',
        'Au moins une minuscule (a-z)',
        'Au moins un chiffre (0-9)',
        'Au moins un caractère spécial',
    ];
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function deliveryPricingLabel(): string {
    return 'Livraison gratuite à Bordeaux. '
        . formatPrice(LIVRAISON_BASE)
        . ' + '
        . number_format(LIVRAISON_KM, 2, ',', ' ')
        . ' €/km au-delà.';
}

function commandeStatusClass(?string $status): string {
    $status = $status ?? '';
    return commandeStatusDefinitions()[$status]['class'] ?? 'statut-en_attente';
}

function commandeCanTransition(?string $from, string $to): bool {
    if ($from === $to) {
        return true;
    }

    $definitions = commandeStatusDefinitions();
    if (!$from || !isset($definitions[$from], $definitions[$to])) {
        return false;
    }

    return in_array($to, $definitions[$from]['transitions'], true);
}

function commandeCanClientModify(array $commande): bool {
    return ($commande['statut'] ?? '') === commandeInitialStatus();
}

function commandeCanClientTrack(?string $status): bool {
    $trackableStatuses = array_diff(
        commandeStatuses(),
        [commandeInitialStatus(), commandeCancelledStatus(), commandeCompletedStatus()]
    );
    return in_array($status, $trackableStatuses, true);
}

function commandeCanReview(?string $status): bool {
    return $status === commandeCompletedStatus();
}

function commandeCountByStatus(array $commandes, string $status): int {
    return count(array_filter($commandes, fn($commande) => ($commande['statut'] ?? '') === $status));
}

function geocodeVille(string $ville): ?array {
    $villeNormalisee = strtolower(trim($ville));
    $fallback = [
        'bordeaux' => [44.8378, -0.5792],
        'merignac' => [44.8448, -0.6564],
        'mérignac' => [44.8448, -0.6564],
        'pessac' => [44.8058, -0.6305],
        'talence' => [44.8088, -0.5892],
        'begles' => [44.8077, -0.5488],
        'bègles' => [44.8077, -0.5488],
        'cenon' => [44.8558, -0.5328],
        'lormont' => [44.8792, -0.5256],
        'floirac' => [44.8327, -0.5278],
        'bruges' => [44.8829, -0.6120],
        'gradignan' => [44.7736, -0.6156],
        "villenave-d'ornon" => [44.7733, -0.5679],
        'villenave d ornon' => [44.7733, -0.5679],
        'le bouscat' => [44.8662, -0.5984],
    ];

    if (isset($fallback[$villeNormalisee])) {
        return $fallback[$villeNormalisee];
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&q=' . urlencode($ville . ', France');
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: ViteGourmand/1.0\r\n",
            'timeout' => 2,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) {
        return null;
    }

    $data = json_decode($response, true);
    if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
        return null;
    }

    return [(float)$data[0]['lat'], (float)$data[0]['lon']];
}

function distanceKmDepuisBordeaux(string $ville): float {
    $coords = geocodeVille($ville);
    if (!$coords) {
        return 0.0;
    }

    [$lat2, $lon2] = $coords;
    $earthRadius = 6371;
    $lat1 = deg2rad(BORDEAUX_LAT);
    $lon1 = deg2rad(BORDEAUX_LNG);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    $deltaLat = $lat2 - $lat1;
    $deltaLon = $lon2 - $lon1;
    $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadius * $c, 2);
}

function calculPrixLivraison(string $ville): float {
    if (strtolower(trim($ville)) === 'bordeaux') return 0.0;
    $distanceKm = distanceKmDepuisBordeaux($ville);
    return round(LIVRAISON_BASE + (LIVRAISON_KM * $distanceKm), 2);
}

function calculPrixMenu(float $prixParPersonne, int $nbPersonnes, int $nbMinimum): float {
    $prix = $prixParPersonne * $nbPersonnes;
    if (($nbPersonnes - $nbMinimum) >= REDUCTION_SEUIL) {
        $prix *= (1 - REDUCTION_TAUX);
    }
    return round($prix, 2);
}

function validatePassword(string $password): bool {
    return strlen($password) >= 10
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[\W_]/', $password);
}
