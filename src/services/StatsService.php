<?php
// src/services/StatsService.php

class StatsService
{
    private const COLLECTION = 'commandes_stats';
    public static function recordCommande(int $commandeId, array $commande, array $menu): void
    {
        if (!commandeCountsTowardRevenue($commande['statut'] ?? null)) {
            return;
        }

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
        $revenueStatuses = commandeRevenueStatuses();
        $placeholders = implode(',', array_fill(0, count($revenueStatuses), '?'));
        $stmt = Database::getConnection()->prepare("
            SELECT cl.ligne_id, cl.commande_id, cl.menu_id, m.titre AS menu_titre,
                   cl.prix_total_ligne, cl.nombre_personne,
                   COALESCE(accept_hist.date_acceptation, c.date_commande) AS date_comptabilisation
            FROM commande c
            LEFT JOIN (
                SELECT commande_id, MIN(created_at) AS date_acceptation
                FROM commande_historique
                WHERE nouveau_statut = ?
                GROUP BY commande_id
            ) accept_hist ON accept_hist.commande_id = c.commande_id
            JOIN commande_ligne cl ON cl.commande_id = c.commande_id
            JOIN menu m ON m.menu_id = cl.menu_id
            WHERE c.statut IN ($placeholders)
        ");
        $stmt->execute(array_merge([commandeAcceptedStatus()], $revenueStatuses));

        $rows = $stmt->fetchAll();
        $eligibleCommandeIds = array_values(array_unique(array_map(fn($row) => (int)$row['commande_id'], $rows)));

        $collection->deleteMany(['ligne_id' => ['$exists' => false]]);
        if ($eligibleCommandeIds) {
            $collection->deleteMany(['commande_id' => ['$nin' => $eligibleCommandeIds]]);
        } else {
            $collection->deleteMany([]);
        }

        foreach ($rows as $row) {
            $createdAt = !empty($row['date_comptabilisation']) ? strtotime($row['date_comptabilisation']) : time();
            $collection->updateOne(
                ['ligne_id' => (int)$row['ligne_id']],
                ['$setOnInsert' => [
                    'ligne_id'     => (int)$row['ligne_id'],
                    'commande_id'  => (int)$row['commande_id'],
                    'menu_id'      => (int)$row['menu_id'],
                    'menu_titre'   => $row['menu_titre'],
                    'prix_total'   => (float)$row['prix_total_ligne'],
                    'nb_personnes' => (int)$row['nombre_personne'],
                    'created_at'   => new \MongoDB\BSON\UTCDateTime($createdAt * 1000),
                ]],
                ['upsert' => true]
            );
        }
    }
}
