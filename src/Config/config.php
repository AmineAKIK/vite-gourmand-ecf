<?php
// src/config/config.php

use App\Config\Environment;

$rootDir = dirname(__DIR__, 2);
$autoload = $rootDir . '/vendor/autoload.php';

require_once __DIR__ . '/Environment.php';

if (file_exists($autoload)) {
    require_once $autoload;

    if (class_exists(\Dotenv\Dotenv::class) && file_exists($rootDir . '/.env')) {
        \Dotenv\Dotenv::createImmutable($rootDir)->safeLoad();
    }
}

define('DB_HOST', Environment::get('DB_HOST', 'localhost'));
define('DB_NAME', Environment::get('DB_NAME', 'vite_gourmand'));
define('DB_USER', Environment::get('DB_USER', 'vg'));
define('DB_PASS', Environment::get('DB_PASS', 'vg'));
define('DB_CHARSET', 'utf8mb4');

define('STRIPE_SECRET_KEY', Environment::get('STRIPE_SECRET_KEY'));
define('STRIPE_PUBLISHABLE_KEY', Environment::get('STRIPE_PUBLISHABLE_KEY'));
define('STRIPE_WEBHOOK_SECRET', Environment::get('STRIPE_WEBHOOK_SECRET'));

define('BREVO_API_KEY', Environment::get('BREVO_API_KEY'));
define('MAIL_FROM', Environment::get('MAIL_FROM', 'noreply@vitegourmand.fr'));
// MAIL_FROM_NAME est un fallback — la vraie valeur vient de siteName() (table site_config)
define('MAIL_FROM_NAME', Environment::get('MAIL_FROM_NAME', 'Mon Traiteur'));

define('BASE_URL', Environment::get('BASE_URL', 'http://localhost:8080'));
define('APP_ENV', Environment::get('APP_ENV', 'production'));
define('APP_VERSION', '1.1.0');
define('APP_NAME', 'Tugères');
define('APP_VENDOR_URL', 'https://tugeres.fr');
define('SAAS_SECRET', Environment::get('SAAS_SECRET'));
define('TUGERES_ENTITLEMENTS_MODE', strtolower(Environment::get('TUGERES_ENTITLEMENTS_MODE', 'legacy')));
define('TUGERES_LICENSE_PUBLIC_KEY_B64', Environment::get('TUGERES_LICENSE_PUBLIC_KEY_B64'));

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
define('LIVRAISON_BASE', 5.00);
define('LIVRAISON_KM',   0.59);
// Valeurs de repli si site_config est indisponible. Source de vérité = table site_config.
define('REDUCTION_TAUX',  0.10);   // 10% (0.10 = fraction, site_config stocke "10" en entier)
