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
    return in_array($_SESSION['user']['role'] ?? '', ['employe', 'administrateur']);
}

function view(string $template, array $data = []): void {
    extract($data);
    $file = __DIR__ . '/views/' . $template . '.php';
    if (!file_exists($file)) { die("Vue introuvable : $template"); }
    ob_start();
    require $file;
    $content = ob_get_clean();
    require __DIR__ . '/views/layouts/main.php';
}

function redirect(string $url): void {
    header("Location: $url"); exit;
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

function verifyCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403); die('CSRF token invalide');
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
