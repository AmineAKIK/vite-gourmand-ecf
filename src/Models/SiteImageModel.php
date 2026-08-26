<?php

namespace App\Models;

use App\Config\Database;
use App\Domain\BrandAsset;

class SiteImageModel
{
    public static function get(BrandAsset $asset): ?string
    {
        $stmt = Database::getConnection()->prepare('SELECT url FROM site_image WHERE cle = ?');
        $stmt->execute([$asset->value]);
        $row = $stmt->fetch();
        return $row ? (string) $row['url'] : null;
    }

    /** @return array<string,string> */
    public static function getAll(): array
    {
        $allowed = BrandAsset::storageKeys();
        $stmt = Database::getConnection()->query('SELECT cle, url FROM site_image');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['cle'];
            if (in_array($key, $allowed, true)) {
                $result[$key] = (string) $row['url'];
            }
        }
        return $result;
    }

    public static function set(BrandAsset $asset, string $url): void
    {
        Database::getConnection()
            ->prepare('INSERT INTO site_image (cle, url) VALUES (?, ?) ON DUPLICATE KEY UPDATE url = ?, updated_at = NOW()')
            ->execute([$asset->value, $url, $url]);
    }
}
