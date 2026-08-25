<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function isConnected(): bool
    {
        return self::$instance !== null;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (PHP_SAPI === 'cli') {
                throw $e;
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Connexion base de données impossible']);
            exit;
        }

        return self::$instance;
    }
}
