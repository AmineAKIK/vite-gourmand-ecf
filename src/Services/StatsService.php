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

    // ----------------------------------------------------------------
    // CA par menu (v_ca_par_menu)
    // ----------------------------------------------------------------

    /**
     * Returns CA ranked by menu for the given filters.
     * Shape: titre, ca (TTC), nb, ca_ht, nb_personnes, prix_moyen_menu.
     */
    public static function getCaParMenu(
        int    $menuId    = 0,
        string $dateDebut = '',
        string $dateFin   = ''
    ): array {
        if ($menuId || $dateDebut || $dateFin) {
            return self::getCaParMenuFiltered($menuId, $dateDebut, $dateFin);
        }

        return self::fetchAll(
            "SELECT menu_id, menu_titre AS titre,
                    nb_commandes AS nb,
                    ca_menu_ttc  AS ca,
                    ca_menu_ht,
                    nb_personnes,
                    prix_moyen_menu
             FROM v_ca_par_menu
             ORDER BY ca DESC"
        );
    }

    private static function getCaParMenuFiltered(int $menuId, string $dateDebut, string $dateFin): array
    {
        $sql    = "
            SELECT
                s.menu_id,
                s.menu_titre                                              AS titre,
                COUNT(DISTINCT s.commande_id)                             AS nb,
                SUM(s.prix_net_menu)                                      AS ca,
                SUM(ROUND(s.prix_net_menu / (1 + s.taux_tva / 100), 2))  AS ca_ht,
                SUM(s.nombre_personne)                                    AS nb_personnes,
                ROUND(AVG(s.prix_net_menu), 2)                            AS prix_moyen_menu
            FROM v_ca_stats s
            WHERE 1=1
        ";
        $params = [];

        if ($menuId) {
            $sql      .= " AND s.menu_id = ?";
            $params[]  = $menuId;
        }
        if ($dateDebut) {
            $sql      .= " AND s.date_comptabilisation >= ?";
            $params[]  = $dateDebut;
        }
        if ($dateFin) {
            $sql      .= " AND s.date_comptabilisation <= ?";
            $params[]  = $dateFin . ' 23:59:59';
        }

        $sql .= " GROUP BY s.menu_id, s.menu_titre ORDER BY ca DESC";
        return self::fetchAll($sql, $params);
    }

    // ----------------------------------------------------------------
    // Tendance mensuelle (v_ca_mensuel)
    // ----------------------------------------------------------------

    /**
     * Returns up to $limit months, most recent first.
     * Shape: annee_mois, nb_commandes, ca_ttc, ca_ht, tva_collectee,
     *        nb_personnes, panier_moyen_ttc.
     */
    public static function getCaMensuel(
        int    $limit     = 12,
        string $dateDebut = '',
        string $dateFin   = ''
    ): array {
        $sql    = "SELECT * FROM v_ca_mensuel WHERE 1=1";
        $params = [];

        if ($dateDebut) {
            $sql     .= " AND annee_mois >= ?";
            $params[] = substr($dateDebut, 0, 7);
        }
        if ($dateFin) {
            $sql     .= " AND annee_mois <= ?";
            $params[] = substr($dateFin, 0, 7);
        }

        $sql .= " ORDER BY annee DESC, mois DESC LIMIT " . max(1, (int)$limit);
        return self::fetchAll($sql, $params);
    }

    // ----------------------------------------------------------------
    // Synthèse globale (v_ca_commandes)
    // ----------------------------------------------------------------

    /**
     * Returns aggregate totals for KPI cards.
     * Shape: total_ttc, total_ht, total_tva, nb_commandes, nb_personnes,
     *        montant_encaisse, solde_restant.
     */
    public static function getSynthese(string $dateDebut = '', string $dateFin = ''): array
    {
        $sql    = "
            SELECT
                COUNT(*)              AS nb_commandes,
                SUM(total_ttc)        AS total_ttc,
                SUM(total_ht)         AS total_ht,
                SUM(total_tva)        AS total_tva,
                SUM(nb_personnes)     AS nb_personnes,
                SUM(montant_encaisse) AS montant_encaisse,
                SUM(solde_restant)    AS solde_restant
            FROM v_ca_commandes
            WHERE 1=1
        ";
        $params = [];

        if ($dateDebut) {
            $sql     .= " AND date_comptabilisation >= ?";
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql     .= " AND date_comptabilisation <= ?";
            $params[] = $dateFin . ' 23:59:59';
        }

        return self::fetchOne($sql, $params) ?: [
            'nb_commandes'     => 0,
            'total_ttc'        => 0,
            'total_ht'         => 0,
            'total_tva'        => 0,
            'nb_personnes'     => 0,
            'montant_encaisse' => 0,
            'solde_restant'    => 0,
        ];
    }

    // ----------------------------------------------------------------
    // Export CSV (v_ca_commandes)
    // ----------------------------------------------------------------

    /**
     * Returns all rows from v_ca_commandes for CSV export.
     */
    public static function getExportRows(string $dateDebut = '', string $dateFin = ''): array
    {
        $sql    = "
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
            $sql     .= " AND date_comptabilisation >= ?";
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql     .= " AND date_comptabilisation <= ?";
            $params[] = $dateFin . ' 23:59:59';
        }

        $sql .= " ORDER BY date_comptabilisation DESC, numero_commande DESC";
        return self::fetchAll($sql, $params);
    }

    // ----------------------------------------------------------------
    // Export détail par lignes (v_ca_stats)
    // Une ligne = un menu dans une commande — pour journal comptable
    // ----------------------------------------------------------------

    public static function getExportLignes(string $dateDebut = '', string $dateFin = ''): array
    {
        $sql    = "
            SELECT
                s.numero_commande,
                s.date_comptabilisation,
                s.date_prestation,
                s.ville_livraison,
                CONCAT(s.client_prenom, ' ', s.client_nom) AS client,
                s.client_email,
                s.menu_titre,
                s.nombre_personne,
                ROUND(s.prix_brut_menu, 2)                AS prix_brut_menu,
                ROUND(s.remise_appliquee, 2)              AS remise,
                ROUND(s.prix_net_menu, 2)                 AS prix_net_menu,
                ROUND(s.prix_livraison, 2)                AS frais_livraison,
                ROUND(s.prix_total_ligne, 2)              AS total_ligne_ttc,
                s.taux_tva,
                ROUND(s.prix_total_ligne_ht, 2)           AS total_ligne_ht,
                ROUND(s.tva_ligne, 2)                     AS tva_ligne,
                s.statut
            FROM v_ca_stats s
            WHERE 1=1
        ";
        $params = [];

        if ($dateDebut) {
            $sql     .= " AND s.date_comptabilisation >= ?";
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql     .= " AND s.date_comptabilisation <= ?";
            $params[] = $dateFin . ' 23:59:59';
        }

        $sql .= " ORDER BY s.date_comptabilisation DESC, s.commande_id ASC, s.ligne_id ASC";
        return self::fetchAll($sql, $params);
    }

    // ----------------------------------------------------------------
    // Export mensuel TVA (v_ca_mensuel)
    // ----------------------------------------------------------------

    public static function getExportMensuel(string $dateDebut = '', string $dateFin = ''): array
    {
        $rows = self::getCaMensuel(120, $dateDebut, $dateFin);
        // Return chronological order for the export
        return array_reverse($rows);
    }
}
