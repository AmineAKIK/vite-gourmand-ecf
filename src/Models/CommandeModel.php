<?php

namespace App\Models;

use App\Config\Database;
use App\Config\PlanConfig;
use App\Domain\OrderStatus;
use App\Models\NotificationModel;
use RuntimeException;
use Throwable;

class CommandeModel
{

    /**
     * $commandeData: numero_commande, utilisateur_id, date_prestation, heure_livraison,
     *                adresse_livraison, ville_livraison, code_postal_livraison, prix_total, prix_livraison
     * $lignes: produit de PricingService::computeOrderTotal()['lignes'], chaque entrée contient :
     *   menu_id, nombre_personne, prix_menu, prix_livraison, prix_total_ligne,
     *   prix_par_personne_snapshot, taux_tva_snapshot, taux_reduction_snapshot,
     *   remise_appliquee, taux_tva_id
     */
    public static function create(array $commandeData, array $lignes): int {
        // Vérification quota plan SaaS (fail-open si DB indisponible)
        PlanConfig::checkCommandesQuota();

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            foreach ($lignes as $ligne) {
                $stockStmt = $db->prepare("SELECT quantite_restante FROM menu WHERE menu_id = ? AND actif = 1 FOR UPDATE");
                $stockStmt->execute([(int)$ligne['menu_id']]);
                $stock = $stockStmt->fetchColumn();
                if ($stock === false || ($stock !== null && (int)$stock <= 0)) {
                    throw new RuntimeException('Stock indisponible pour l\'un des menus.');
                }
            }

            $stmt = $db->prepare("
                INSERT INTO commande (numero_commande, utilisateur_id, date_prestation,
                    heure_livraison, adresse_livraison, ville_livraison, code_postal_livraison, prix_total, instructions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $commandeData['numero_commande'],
                $commandeData['utilisateur_id'],
                $commandeData['date_prestation'],
                $commandeData['heure_livraison'],
                $commandeData['adresse_livraison'],
                $commandeData['ville_livraison'],
                $commandeData['code_postal_livraison'],
                $commandeData['prix_total'],
                $commandeData['instructions'] ?? null,
            ]);
            $commandeId = (int)$db->lastInsertId();

            $ligneStmt = $db->prepare("
                INSERT INTO commande_ligne (
                    commande_id, menu_id, nombre_personne,
                    prix_menu, prix_livraison, prix_total_ligne,
                    prix_par_personne_snapshot, taux_tva_snapshot,
                    taux_reduction_snapshot, remise_appliquee, taux_tva_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($lignes as $ligne) {
                $ligneStmt->execute([
                    $commandeId,
                    (int)$ligne['menu_id'],
                    (int)$ligne['nombre_personne'],
                    (float)$ligne['prix_menu'],
                    (float)$ligne['prix_livraison'],
                    (float)$ligne['prix_total_ligne'],
                    (float)($ligne['prix_par_personne_snapshot'] ?? 0),
                    (float)($ligne['taux_tva_snapshot']          ?? 10.0),
                    (float)($ligne['taux_reduction_snapshot']    ?? 0),
                    (float)($ligne['remise_appliquee']           ?? 0),
                    isset($ligne['taux_tva_id']) ? (int)$ligne['taux_tva_id'] : null,
                ]);

                $stockStmt2 = $db->prepare("SELECT quantite_restante FROM menu WHERE menu_id = ?");
                $stockStmt2->execute([(int)$ligne['menu_id']]);
                $stock = $stockStmt2->fetchColumn();
                if ($stock !== null) {
                    $db->prepare("UPDATE menu SET quantite_restante = quantite_restante - 1 WHERE menu_id = ?")
                       ->execute([(int)$ligne['menu_id']]);
                }
            }

            self::addHistorique($commandeId, null, OrderStatus::initial(), 'Commande passée', $commandeData['utilisateur_id']);
            $db->commit();

            // Notification employés/admins (hors transaction — fail silencieux)
            $clientNom = trim(($commandeData['prenom'] ?? '') . ' ' . ($commandeData['nom'] ?? ''));
            NotificationModel::notifyEmployesNouvelleCommande(
                $commandeId,
                $commandeData['numero_commande'],
                $clientNom ?: 'Client'
            );

            return $commandeId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getLignes(int $commandeId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT cl.*, m.titre AS menu_titre, m.prix_par_personne, m.nombre_personne_minimum
            FROM commande_ligne cl
            JOIN menu m ON m.menu_id = cl.menu_id
            WHERE cl.commande_id = ?
            ORDER BY cl.ligne_id ASC
        ");
        $stmt->execute([$commandeId]);
        return $stmt->fetchAll();
    }

    public static function getLignesByCommandes(array $commandeIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $commandeIds))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare("
            SELECT cl.*, m.titre AS menu_titre, m.prix_par_personne, m.nombre_personne_minimum
            FROM commande_ligne cl
            JOIN menu m ON m.menu_id = cl.menu_id
            WHERE cl.commande_id IN ($placeholders)
            ORDER BY cl.commande_id ASC, cl.ligne_id ASC
        ");
        $stmt->execute($ids);

        $lignes = [];
        foreach ($stmt->fetchAll() as $ligne) {
            $lignes[(int)$ligne['commande_id']][] = $ligne;
        }
        return $lignes;
    }

    public static function getByUser(int $userId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*,
                   GROUP_CONCAT(m.titre ORDER BY cl.ligne_id SEPARATOR ', ') AS menu_titre
            FROM commande c
            JOIN commande_ligne cl ON cl.commande_id = c.commande_id
            JOIN menu m ON m.menu_id = cl.menu_id
            WHERE c.utilisateur_id = ?
            GROUP BY c.commande_id
            ORDER BY c.date_commande DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*,
                   GROUP_CONCAT(m.titre ORDER BY cl.ligne_id SEPARATOR ', ') AS menu_titre,
                   u.email, u.prenom, u.nom, u.telephone
            FROM commande c
            JOIN commande_ligne cl ON cl.commande_id = c.commande_id
            JOIN menu m ON m.menu_id = cl.menu_id
            JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
            WHERE c.commande_id = ?
            GROUP BY c.commande_id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getAll(array $filters = []): array {
        $db  = Database::getConnection();
        $sql = "
            SELECT c.*,
                   accept_hist.date_acceptation,
                   GROUP_CONCAT(m.titre ORDER BY cl.ligne_id SEPARATOR ', ') AS menu_titre,
                   u.prenom, u.nom, u.email, u.telephone
            FROM commande c
            LEFT JOIN (
                SELECT commande_id, MIN(created_at) AS date_acceptation
                FROM commande_historique
                WHERE nouveau_statut = ?
                GROUP BY commande_id
            ) accept_hist ON accept_hist.commande_id = c.commande_id
            JOIN commande_ligne cl ON cl.commande_id = c.commande_id
            JOIN menu m ON m.menu_id = cl.menu_id
            JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
            WHERE 1=1
        ";
        $params = [OrderStatus::accepted()];
        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = ?"; $params[] = $filters['statut'];
        }
        if (!empty($filters['q'])) {
            $sql .= " AND (
                u.nom LIKE ?
                OR u.prenom LIKE ?
                OR u.email LIKE ?
                OR u.telephone LIKE ?
                OR c.numero_commande LIKE ?
                OR c.ville_livraison LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM commande_ligne cl_search
                    JOIN menu m_search ON m_search.menu_id = cl_search.menu_id
                    WHERE cl_search.commande_id = c.commande_id
                    AND m_search.titre LIKE ?
                )
            )";
            $searchLike = '%' . $filters['q'] . '%';
            array_push($params, ...array_fill(0, 7, $searchLike));
        }
        if (!empty($filters['client']) && empty($filters['q'])) {
            $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
            $clientLike = '%' . $filters['client'] . '%';
            array_push($params, ...array_fill(0, 3, $clientLike));
        }
        if (!empty($filters['periode'])) {
            $today = date('Y-m-d');
            switch ($filters['periode']) {
                case 'today':
                    $sql .= " AND c.date_prestation = ?";
                    $params[] = $today;
                    break;
                case 'tomorrow':
                    $sql .= " AND c.date_prestation = ?";
                    $params[] = date('Y-m-d', strtotime('+1 day'));
                    break;
                case 'week':
                    $sql .= " AND c.date_prestation BETWEEN ? AND ?";
                    $params[] = $today;
                    $params[] = date('Y-m-d', strtotime('+7 days'));
                    break;
                case 'upcoming':
                    $sql .= " AND c.date_prestation >= ?";
                    $params[] = $today;
                    break;
                case 'past':
                    $sql .= " AND c.date_prestation < ?";
                    $params[] = $today;
                    break;
                case 'this_month':
                    $sql .= " AND c.date_prestation BETWEEN ? AND ?";
                    $params[] = date('Y-m-01');
                    $params[] = date('Y-m-t');
                    break;
                case 'last_month':
                    $sql .= " AND c.date_prestation BETWEEN ? AND ?";
                    $params[] = date('Y-m-01', strtotime('first day of last month'));
                    $params[] = date('Y-m-t', strtotime('last day of last month'));
                    break;
            }
        }
        if (($filters['periode'] ?? '') === 'custom') {
            if (!empty($filters['date_debut'])) {
                $sql .= " AND c.date_prestation >= ?";
                $params[] = $filters['date_debut'];
            }
            if (!empty($filters['date_fin'])) {
                $sql .= " AND c.date_prestation <= ?";
                $params[] = $filters['date_fin'];
            }
        }
        if (!empty($filters['menu_id'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM commande_ligne cl_filter
                WHERE cl_filter.commande_id = c.commande_id
                AND cl_filter.menu_id = ?
            )";
            $params[] = (int)$filters['menu_id'];
        }
        if (!empty($filters['ville'])) {
            $sql .= " AND c.ville_livraison LIKE ?";
            $params[] = '%' . $filters['ville'] . '%';
        }
        if (!empty($filters['montant'])) {
            switch ($filters['montant']) {
                case 'moins_250':
                    $sql .= " AND c.prix_total < 250";
                    break;
                case '250_1000':
                    $sql .= " AND c.prix_total BETWEEN 250 AND 1000";
                    break;
                case 'plus_1000':
                    $sql .= " AND c.prix_total > 1000";
                    break;
            }
        }
        $orderBy = match ($filters['tri'] ?? '') {
            'date_prestation_desc' => 'c.date_prestation DESC, c.date_commande DESC',
            'commande_recente' => 'c.date_commande DESC',
            'montant_desc' => 'c.prix_total DESC, c.date_prestation ASC',
            'montant_asc' => 'c.prix_total ASC, c.date_prestation ASC',
            'client_asc' => 'u.nom ASC, u.prenom ASC, c.date_prestation ASC',
            default => 'c.date_prestation ASC, c.date_commande DESC',
        };
        $sql .= " GROUP BY c.commande_id ORDER BY $orderBy";

        if (isset($filters['limit']) && (int)$filters['limit'] > 0) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (isset($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retourne le nombre total de commandes correspondant aux filtres (sans LIMIT/OFFSET ni tri).
     * Utilise les mêmes conditions WHERE que getAll().
     */
    public static function countAll(array $filters = []): int {
        $db  = Database::getConnection();
        $sql = "
            SELECT COUNT(DISTINCT c.commande_id)
            FROM commande c
            JOIN commande_ligne cl ON cl.commande_id = c.commande_id
            JOIN menu m ON m.menu_id = cl.menu_id
            JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = ?"; $params[] = $filters['statut'];
        }
        if (!empty($filters['q'])) {
            $sql .= " AND (
                u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?
                OR c.numero_commande LIKE ? OR c.ville_livraison LIKE ?
                OR EXISTS (
                    SELECT 1 FROM commande_ligne cl_s JOIN menu m_s ON m_s.menu_id = cl_s.menu_id
                    WHERE cl_s.commande_id = c.commande_id AND m_s.titre LIKE ?
                )
            )";
            $like = '%' . $filters['q'] . '%';
            array_push($params, ...array_fill(0, 7, $like));
        }
        if (!empty($filters['periode'])) {
            $today = date('Y-m-d');
            match ($filters['periode']) {
                'today'      => ($sql .= " AND c.date_prestation = ?" ) && ($params[] = $today),
                'tomorrow'   => ($sql .= " AND c.date_prestation = ?" ) && ($params[] = date('Y-m-d', strtotime('+1 day'))),
                'week'       => ($sql .= " AND c.date_prestation BETWEEN ? AND ?") && array_push($params, $today, date('Y-m-d', strtotime('+7 days'))),
                'upcoming'   => ($sql .= " AND c.date_prestation >= ?") && ($params[] = $today),
                'past'       => ($sql .= " AND c.date_prestation < ?")  && ($params[] = $today),
                'this_month' => ($sql .= " AND c.date_prestation BETWEEN ? AND ?") && array_push($params, date('Y-m-01'), date('Y-m-t')),
                'last_month' => ($sql .= " AND c.date_prestation BETWEEN ? AND ?") && array_push($params, date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))),
                default      => null,
            };
        }
        if (($filters['periode'] ?? '') === 'custom') {
            if (!empty($filters['date_debut'])) { $sql .= " AND c.date_prestation >= ?"; $params[] = $filters['date_debut']; }
            if (!empty($filters['date_fin']))   { $sql .= " AND c.date_prestation <= ?"; $params[] = $filters['date_fin'];   }
        }
        if (!empty($filters['menu_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM commande_ligne cl_f WHERE cl_f.commande_id = c.commande_id AND cl_f.menu_id = ?)";
            $params[] = (int)$filters['menu_id'];
        }
        if (!empty($filters['ville'])) {
            $sql .= " AND c.ville_livraison LIKE ?"; $params[] = '%' . $filters['ville'] . '%';
        }
        if (!empty($filters['montant'])) {
            match ($filters['montant']) {
                'moins_250'  => $sql .= " AND c.prix_total < 250",
                '250_1000'   => $sql .= " AND c.prix_total BETWEEN 250 AND 1000",
                'plus_1000'  => $sql .= " AND c.prix_total > 1000",
                default      => null,
            };
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function countByDate(string $date): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM commande WHERE date_prestation = ? AND statut != 'annule'");
        $stmt->execute([$date]);
        return (int)$stmt->fetchColumn();
    }

    public static function updateStatut(int $id, string $statut, ?string $commentaire, int $modifiePar): void {
        $db   = Database::getConnection();
        $old  = self::getById($id);
        $db->prepare("UPDATE commande SET statut=? WHERE commande_id=?")->execute([$statut, $id]);
        self::addHistorique($id, $old['statut'], $statut, $commentaire, $modifiePar);
    }

    public static function updateDetails(int $id, array $data): void {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE commande SET date_prestation=?, heure_livraison=?, adresse_livraison=?,
            ville_livraison=?, code_postal_livraison=?, prix_total=?, instructions=? WHERE commande_id=?
        ");
        $stmt->execute([
            $data['date_prestation'],
            $data['heure_livraison'],
            $data['adresse_livraison'],
            $data['ville_livraison'],
            $data['code_postal_livraison'],
            $data['prix_total'],
            $data['instructions'] ?? null,
            $id,
        ]);
    }

    public static function cancel(int $id, string $motif, string $modeContact, int $modifiePar): void {
        $db   = Database::getConnection();
        $old  = self::getById($id);
        $db->prepare("UPDATE commande SET statut=?, motif_annulation=?, mode_contact_annulation=? WHERE commande_id=?")
           ->execute([OrderStatus::cancelled(), $motif, $modeContact, $id]);
        self::addHistorique($id, $old['statut'] ?? null, OrderStatus::cancelled(), "Annulation ($modeContact) : $motif", $modifiePar);
    }

    public static function getHistorique(int $commandeId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT h.*, u.prenom, u.nom
            FROM commande_historique h
            LEFT JOIN utilisateur u ON u.utilisateur_id = h.modifie_par
            WHERE h.commande_id = ?
            ORDER BY h.created_at ASC
        ");
        $stmt->execute([$commandeId]);
        return $stmt->fetchAll();
    }

    public static function addHistorique(int $commandeId, ?string $ancien, string $nouveau, ?string $commentaire, ?int $modifiePar): void {
        $db = Database::getConnection();
        $db->prepare("INSERT INTO commande_historique (commande_id, ancien_statut, nouveau_statut, commentaire, modifie_par) VALUES (?,?,?,?,?)")
           ->execute([$commandeId, $ancien, $nouveau, $commentaire, $modifiePar]);
    }

    public static function canModify(array $commande): bool
    {
        return OrderStatus::clientCanModify($commande);
    }
}
