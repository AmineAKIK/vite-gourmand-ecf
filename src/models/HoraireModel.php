<?php
// src/models/HoraireModel.php

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
                sanitize($horaire['ouverture'] ?? ''),
                sanitize($horaire['fermeture'] ?? ''),
                (int)$id,
            ]);
        }
    }
}
