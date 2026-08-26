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

    public static function getCaParMenu(int $menuId = 0, string $dateDebut = '', string $dateFin = ''): array
    {
        if ($menuId || $dateDebut || $dateFin) {
            return self::getCaParMenuFiltered($menuId, $dateDebut, $dateFin);
        }

        return self::fetchAll(
            "SELECT menu_id, menu_titre AS titre, currency,
                    nb_commandes AS nb,
                    ca_menu_ttc_cents AS ca_cents,
                    ca_menu_ht_cents AS ca_ht_cents,
                    nb_personnes,
                    prix_moyen_menu_cents
             FROM v_ca_par_menu
             ORDER BY ca_menu_ttc_cents DESC",
        );
    }

    private static function getCaParMenuFiltered(int $menuId, string $dateDebut, string $dateFin): array
    {
        $sql = "
            SELECT
                s.menu_id,
                s.menu_titre AS titre,
                s.currency,
                COUNT(DISTINCT s.commande_id) AS nb,
                SUM(s.prix_net_menu_cents) AS ca_cents,
                SUM(CAST(ROUND(s.prix_net_menu_cents * 10000 / (10000 + s.taux_tva_menu_basis_points)) AS SIGNED)) AS ca_ht_cents,
                SUM(s.nombre_personne) AS nb_personnes,
                CAST(ROUND(AVG(s.prix_net_menu_cents)) AS SIGNED) AS prix_moyen_menu_cents
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
        $sql .= ' GROUP BY s.menu_id, s.menu_titre, s.currency ORDER BY ca_cents DESC';
        return self::fetchAll($sql, $params);
    }

    public static function getCaMensuel(int $limit = 12, string $dateDebut = '', string $dateFin = ''): array
    {
        if (!$dateDebut && !$dateFin) {
            return self::fetchAll(
                'SELECT * FROM v_ca_mensuel ORDER BY annee DESC, mois DESC LIMIT ' . max(1, $limit),
            );
        }

        $sql = "
            SELECT
                YEAR(date_comptabilisation) AS annee,
                MONTH(date_comptabilisation) AS mois,
                DATE_FORMAT(date_comptabilisation, '%Y-%m') AS annee_mois,
                currency,
                COUNT(DISTINCT commande_id) AS nb_commandes,
                SUM(total_ttc_cents) AS ca_ttc_cents,
                SUM(total_ht_cents) AS ca_ht_cents,
                SUM(total_tva_cents) AS tva_collectee_cents,
                SUM(nb_personnes) AS nb_personnes,
                CAST(ROUND(SUM(total_ttc_cents) / COUNT(DISTINCT commande_id)) AS SIGNED) AS panier_moyen_ttc_cents
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
            GROUP BY YEAR(date_comptabilisation), MONTH(date_comptabilisation), DATE_FORMAT(date_comptabilisation, '%Y-%m'), currency
            ORDER BY annee DESC, mois DESC
            LIMIT " . max(1, $limit);
        return self::fetchAll($sql, $params);
    }

    public static function getSynthese(string $dateDebut = '', string $dateFin = ''): array
    {
        $sql = "
            SELECT
                COUNT(*) AS nb_commandes,
                MAX(currency) AS currency,
                SUM(total_ttc_cents) AS total_ttc_cents,
                SUM(total_ht_cents) AS total_ht_cents,
                SUM(total_tva_cents) AS total_tva_cents,
                SUM(nb_personnes) AS nb_personnes,
                SUM(montant_encaisse_cents) AS montant_encaisse_cents,
                SUM(solde_restant_cents) AS solde_restant_cents
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
            'currency' => null,
            'total_ttc_cents' => 0,
            'total_ht_cents' => 0,
            'total_tva_cents' => 0,
            'nb_personnes' => 0,
            'montant_encaisse_cents' => 0,
            'solde_restant_cents' => 0,
        ];
    }

    /** @return iterable<array<string,mixed>> */
    public static function getExportRows(string $dateDebut = '', string $dateFin = ''): iterable
    {
        $sql = "
            SELECT numero_commande, date_comptabilisation, date_prestation, ville_livraison,
                   CONCAT(client_prenom, ' ', client_nom) AS client, client_email, nb_personnes, currency,
                   total_ht_cents, total_tva_cents, total_ttc_cents,
                   montant_encaisse_cents, solde_restant_cents, statut_paiement, statut
            FROM v_ca_commandes WHERE 1=1
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
            SELECT s.numero_commande, s.date_comptabilisation, s.date_prestation, s.ville_livraison,
                   CONCAT(s.client_prenom, ' ', s.client_nom) AS client, s.client_email, s.currency,
                   s.menu_titre, s.nombre_personne,
                   s.prix_brut_menu_cents, s.remise_appliquee_cents AS remise_cents,
                   s.prix_net_menu_cents, s.prix_livraison_cents AS frais_livraison_cents,
                   s.prix_total_ligne_cents AS total_ligne_ttc_cents,
                   s.taux_tva_menu_basis_points,
                   s.prix_total_ligne_ht_cents AS total_ligne_ht_cents,
                   s.tva_ligne_cents, s.statut
            FROM v_ca_stats s WHERE 1=1
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
