<?php

namespace App\Models;

use App\Config\Database;

class HoraireModel
{
    public static function getAll(): array
    {
        return Database::getConnection()
            ->query("SELECT * FROM horaire ORDER BY horaire_id")
            ->fetchAll();
    }

    public static function updateMany(array $horaires): void
    {
        $stmt = Database::getConnection()->prepare("
            UPDATE horaire SET heure_ouverture = ?, heure_fermeture = ?
            WHERE horaire_id = ?
        ");

        foreach ($horaires as $id => $horaire) {
            $stmt->execute([
                htmlspecialchars(trim($horaire['ouverture'] ?? ''), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars(trim($horaire['fermeture'] ?? ''), ENT_QUOTES, 'UTF-8'),
                (int)$id,
            ]);
        }
    }
}
