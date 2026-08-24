<?php

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($uri);

// Les archives de facturation historiques ne doivent jamais être servies comme fichiers statiques,
// même si elles existent encore physiquement sous public/uploads/facturation pendant une migration.
if ($decodedPath === '/uploads/facturation' || str_starts_with($decodedPath, '/uploads/facturation/')) {
    http_response_code(404);
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo 'Not Found';
    return true;
}

$publicRoot = realpath(__DIR__);
$candidate = realpath(__DIR__ . $decodedPath);

// Laisser le serveur PHP servir uniquement les vrais fichiers situés à l'intérieur du webroot.
if (
    $publicRoot !== false
    && $candidate !== false
    && str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($candidate)
) {
    return false;
}

require __DIR__ . '/index.php';
return true;
