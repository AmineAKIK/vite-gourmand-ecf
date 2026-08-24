<?php

require_once __DIR__ . '/../src/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

try {
    $db = Database::getConnection();
    $db->query('SELECT 1')->fetchColumn();
    $db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

    http_response_code(200);
    echo json_encode(['status' => 'ready'], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[Readiness] ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'not_ready'], JSON_UNESCAPED_SLASHES);
}
