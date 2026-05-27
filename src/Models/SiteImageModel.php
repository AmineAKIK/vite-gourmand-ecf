<?php

namespace App\Models;

use App\Config\Database;

class SiteImageModel
{
    public static function get(string $cle): ?string
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT url FROM site_image WHERE cle = ?'
        );
        $stmt->execute([$cle]);
        $row = $stmt->fetch();
        return $row ? $row['url'] : null;
    }

    public static function getAll(): array
    {
        $stmt = Database::getConnection()->query('SELECT cle, url FROM site_image');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['cle']] = $row['url'];
        }
        return $result;
    }

    public static function set(string $cle, string $url): void
    {
        Database::getConnection()
            ->prepare('INSERT INTO site_image (cle, url) VALUES (?, ?) ON DUPLICATE KEY UPDATE url = ?, updated_at = NOW()')
            ->execute([$cle, $url, $url]);
    }
}
