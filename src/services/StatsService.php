<?php
// src/services/StatsService.php

class StatsService
{
    private const COLLECTION = 'commandes_stats';
    public static function recordCommande(int $commandeId, array $commande, array $menu): void
    {
        try {
            $collection = self::collection();
            if (!$collection) {
                return;
            }

            $collection->insertOne([
                'commande_id'  => $commandeId,
                'menu_id'      => (int)$commande['menu_id'],
                'menu_titre'   => $menu['titre'],
                'prix_total'   => (float)$commande['prix_total'],
                'nb_personnes' => (int)$commande['nombre_personne'],
                'created_at'   => new \MongoDB\BSON\UTCDateTime(),
            ]);
        } catch (\Throwable $e) {
            error_log('MongoDB insert failed: ' . $e->getMessage());
        }
    }

    public static function getCommandesByMenu(): array
    {
        $stats = [];

        try {
            $collection = self::collection();
            if (!$collection) {
                return [];
            }

            self::syncFromMysql($collection);
            $cursor = $collection->aggregate([
                ['$group' => ['_id' => '$menu_titre', 'total' => ['$sum' => 1]]],
                ['$sort' => ['total' => -1]],
            ]);

            foreach ($cursor as $doc) {
                $stats[] = [
                    'titre'        => (string)$doc['_id'],
                    'nb_commandes' => (int)$doc['total'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('MongoDB stats failed: ' . $e->getMessage());
        }

        return $stats;
    }

    private static ?\MongoDB\Collection $collection = null;

    private static function collection(): mixed
    {
        if (self::$collection !== null) {
            return self::$collection;
        }
        $mongoUri = defined('MONGO_URI') ? MONGO_URI : ($_ENV['MONGO_URI'] ?? null);
        if (!$mongoUri || !class_exists(\MongoDB\Client::class)) {
            return null;
        }
        $client = new \MongoDB\Client($mongoUri);
        self::$collection = $client->selectCollection(MONGO_DB, self::COLLECTION);
        return self::$collection;
    }

    private static function syncFromMysql(mixed $collection): void
    {
        $stmt = Database::getConnection()->prepare("
            SELECT c.commande_id, c.menu_id, m.titre AS menu_titre, c.prix_total,
                   c.nombre_personne, c.date_commande
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            WHERE c.statut != ?
        ");
        $stmt->execute([commandeCancelledStatus()]);

        foreach ($stmt->fetchAll() as $row) {
            $createdAt = !empty($row['date_commande']) ? strtotime($row['date_commande']) : time();
            $collection->updateOne(
                ['commande_id' => (int)$row['commande_id']],
                ['$setOnInsert' => [
                    'commande_id'  => (int)$row['commande_id'],
                    'menu_id'      => (int)$row['menu_id'],
                    'menu_titre'   => $row['menu_titre'],
                    'prix_total'   => (float)$row['prix_total'],
                    'nb_personnes' => (int)$row['nombre_personne'],
                    'created_at'   => new \MongoDB\BSON\UTCDateTime($createdAt * 1000),
                ]],
                ['upsert' => true]
            );
        }
    }
}
