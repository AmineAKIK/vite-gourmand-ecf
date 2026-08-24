<?php

namespace App\Services;

use App\Config\Database;

class StatsService
{
    private static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private static function fetchOne(string $sql, array $params = []): array|false
    {
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    /** @return iterable<array<string,mixed>> */
    private static function iterate(string $sql, array $params = []): iterable
    {
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    /**
     * Returns menu sales ranked by menu for the given filters.
     * `ca` and `ca_ht` are menu-only amounts: delivery is intentionally excluded.
     */
    public static function getCaParMenu(
        int $menuId = 0,
        string $dateDebut = '',
        string $dateFin = '',
    ): array {
        if ($menuId || $dateDebut || $dateFin) {
            return self::getCaParMenuFiltered($menuId, $dateDebut, $dateFin);
        }

        return self::fetchAll(
            "SELECT menu_id, menu_titre AS titre,
                    nb_commandes AS nb,
                    ca_menu_ttc AS ca,
                    ca_menu_ht,
                    nb_personnes,
                    prix_moyen_menu
             FROM v_ca_par_menu
             ORDER BY ca DESC",
        );
    }

    private static function getCaParMenuFiltered(int $menuId, string $dateDebut, string $dateFin): array
    {
        $sql = "
            SELECT
                s.menu_id,
                s.menu_titre AS titre,
                COUNT(DISTINCT s.commande_id) AS nb,
                SUM(s.prix_net_menu) AS ca,
                SUM(ROUND(s.prix_net_menu / (1 + s.taux_tva / 100), 2)) AS ca_ht,
                SUM(s.nombre_personne) AS nb_personnes,
                ROUND(AVG(s.prix_net_menu), 2) AS prix_moyen_menu
            FROM v_ca_stats s
            WHERE 1=1
        ";
        $params = [];

        if ($menuId) {
            $sql .= ' AND s.menu_id = ?';
            $params[] = $menuId;
        }
        if ($dateDebut) {
            $sql .= ' AND s.date_comptabilisation >= ?';
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql .= ' AND s.date_comptabilisation <= ?';
            $params[] = $dateFin . ' 23:59:59';
        }

        $sql .= ' GROUP BY s.menu_id, s.menu_titre ORDER BY ca DESC';

        return self::fetchAll($sql, $params);
    }

    /**
     * Returns up to $limit months, most recent first.
     * Exact date filters are applied before aggregation, including partial months.
     */
    public static function getCaMensuel(
        int $limit = 12,
        string $dateDebut = '',
        string $dateFin = '',
    ): array {
        if (!$dateDebut && !$dateFin) {
            return self::fetchAll(
                'SELECT * FROM v_ca_mensuel ORDER BY annee DESC, mois DESC LIMIT ' . max(1, (int) $limit),
            );
        }

        $sql = "
            SELECT
                YEAR(date_comptabilisation) AS annee,
                MONTH(date_comptabilisation) AS mois,
                DATE_FORMAT(date_comptabilisation, '%Y-%m') AS annee_mois,
                COUNT(DISTINCT commande_id) AS nb_commandes,
                SUM(total_ttc) AS ca_ttc,
                SUM(total_ht) AS ca_ht,
                SUM(total_tva) AS tva_collectee,
                SUM(nb_personnes) AS nb_personnes,
                ROUND(SUM(total_ttc) / COUNT(DISTINCT commande_id), 2) AS panier_moyen_ttc
            FROM v_ca_commandes
            WHERE 1=1
        ";
        $params = [];
        if ($dateDebut) {
            $sql .= ' AND date_comptabilisation >= ?';
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql .= ' AND date_comptabilisation <= ?';
            $params[] = $dateFin . ' 23:59:59';
        }
        $sql .= "
            GROUP BY YEAR(date_comptabilisation), MONTH(date_comptabilisation), DATE_FORMAT(date_comptabilisation, '%Y-%m')
            ORDER BY annee DESC, mois DESC
            LIMIT " . max(1, (int) $limit);

        return self::fetchAll($sql, $params);
    }

    /**
     * Returns aggregate command totals for KPI cards. Delivery is included in command totals.
     */
    public static function getSynthese(string $dateDebut = '', string $dateFin = ''): array
    {
        $sql = "
            SELECT
                COUNT(*) AS nb_commandes,
                SUM(total_ttc) AS total_ttc,
                SUM(total_ht) AS total_ht,
                SUM(total_tva) AS total_tva,
                SUM(nb_personnes) AS nb_personnes,
                SUM(montant_encaisse) AS montant_encaisse,
                SUM(solde_restant) AS solde_restant
            FROM v_ca_commandes
            WHERE 1=1
        ";
        $params = [];

        if ($dateDebut) {
            $sql .= ' AND date_comptabilisation >= ?';
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql .= ' AND date_comptabilisation <= ?';
            $params[] = $dateFin . ' 23:59:59';
        }

        return self::fetchOne($sql, $params) ?: [
            'nb_commandes' => 0,
            'total_ttc' => 0,
            'total_ht' => 0,
            'total_tva' => 0,
            'nb_personnes' => 0,
            'montant_encaisse' => 0,
            'solde_restant' => 0,
        ];
    }

    /** @return iterable<array<string,mixed>> */
    public static function getExportRows(string $dateDebut = '', string $dateFin = ''): iterable
    {
        $sql = "
            SELECT
                numero_commande,
                date_comptabilisation,
                date_prestation,
                ville_livraison,
                CONCAT(client_prenom, ' ', client_nom) AS client,
                client_email,
                nb_personnes,
                total_ht,
                total_tva,
                total_ttc,
                montant_encaisse,
                solde_restant,
                statut_paiement,
                statut
            FROM v_ca_commandes
            WHERE 1=1
        ";
        $params = [];

        if ($dateDebut) {
            $sql .= ' AND date_comptabilisation >= ?';
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql .= ' AND date_comptabilisation <= ?';
            $params[] = $dateFin . ' 23:59:59';
        }

        $sql .= ' ORDER BY date_comptabilisation DESC, numero_commande DESC';

        return self::iterate($sql, $params);
    }

    /** @return iterable<array<string,mixed>> */
    public static function getExportLignes(string $dateDebut = '', string $dateFin = ''): iterable
    {
        $sql = "
            SELECT
                s.numero_commande,
                s.date_comptabilisation,
                s.date_prestation,
                s.ville_livraison,
                CONCAT(s.client_prenom, ' ', s.client_nom) AS client,
                s.client_email,
                s.menu_titre,
                s.nombre_personne,
                ROUND(s.prix_brut_menu, 2) AS prix_brut_menu,
                ROUND(s.remise_appliquee, 2) AS remise,
                ROUND(s.prix_net_menu, 2) AS prix_net_menu,
                ROUND(s.prix_livraison, 2) AS frais_livraison,
                ROUND(s.prix_total_ligne, 2) AS total_ligne_ttc,
                s.taux_tva,
                ROUND(s.prix_total_ligne_ht, 2) AS total_ligne_ht,
                ROUND(s.tva_ligne, 2) AS tva_ligne,
                s.statut
            FROM v_ca_stats s
            WHERE 1=1
        ";
        $params = [];

        if ($dateDebut) {
            $sql .= ' AND s.date_comptabilisation >= ?';
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql .= ' AND s.date_comptabilisation <= ?';
            $params[] = $dateFin . ' 23:59:59';
        }

        $sql .= ' ORDER BY s.date_comptabilisation DESC, s.commande_id ASC, s.ligne_id ASC';

        return self::iterate($sql, $params);
    }

    public static function getExportMensuel(string $dateDebut = '', string $dateFin = ''): array
    {
        return array_reverse(self::getCaMensuel(120, $dateDebut, $dateFin));
    }
}
