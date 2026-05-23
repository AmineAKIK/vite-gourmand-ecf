<?php
// src/config/config.php

$rootDir = dirname(__DIR__, 2);
$autoload = $rootDir . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;

    if (class_exists(\Dotenv\Dotenv::class) && file_exists($rootDir . '/.env')) {
        \Dotenv\Dotenv::createImmutable($rootDir)->safeLoad();
    }
}

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'vite_gourmand');
define('DB_USER', $_ENV['DB_USER'] ?? 'vg');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'vg');
define('DB_CHARSET', 'utf8mb4');

define('MONGO_URI', $_ENV['MONGO_URI'] ?? 'mongodb://localhost:27017');
define('MONGO_DB',  $_ENV['MONGO_DB']  ?? 'vite_gourmand_stats');

define('BREVO_API_KEY', $_ENV['BREVO_API_KEY'] ?? '');
define('MAIL_FROM',     $_ENV['MAIL_FROM']     ?? 'noreply@vitegourmand.fr');
define('MAIL_FROM_NAME','Vite & Gourmand');

define('BASE_URL', $_ENV['BASE_URL'] ?? 'http://localhost:8080');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');

if (APP_ENV !== 'development') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
define('ROLE_USER', 'utilisateur');
define('ROLE_EMPLOYE', 'employe');
define('ROLE_ADMIN', 'administrateur');
define('ROLE_ID_USER', 1);
define('ROLE_ID_EMPLOYE', 2);
define('ROLE_ID_ADMIN', 3);
define('BORDEAUX_LAT', 44.8378);
define('BORDEAUX_LNG', -0.5792);
define('LIVRAISON_BASE', 5.00);
define('LIVRAISON_KM',   0.59);
define('REDUCTION_SEUIL', 5);      // +5 personnes au-dessus du minimum
define('REDUCTION_TAUX',  0.10);   // 10%
