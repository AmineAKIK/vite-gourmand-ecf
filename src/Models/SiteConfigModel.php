<?php

namespace App\Models;

use App\Config\Database;

class SiteConfigModel
{
    public static function get(string $cle, string $default = ''): string
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT valeur FROM site_config WHERE cle = ?'
        );
        $stmt->execute([$cle]);
        $row = $stmt->fetch();
        return $row ? $row['valeur'] : $default;
    }

    public static function getAll(): array
    {
        $stmt = Database::getConnection()->query('SELECT cle, valeur FROM site_config');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['cle']] = $row['valeur'];
        }
        return $result;
    }

    public static function set(string $cle, string $valeur): void
    {
        Database::getConnection()
            ->prepare('INSERT INTO site_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?, updated_at = NOW()')
            ->execute([$cle, $valeur, $valeur]);
    }
}
