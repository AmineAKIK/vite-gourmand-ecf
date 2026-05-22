<?php
// src/models/CommandeModel.php

class CommandeModel {

    public static function create(array $data): int {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stockStmt = $db->prepare("SELECT quantite_restante FROM menu WHERE menu_id = ? AND actif = 1 FOR UPDATE");
            $stockStmt->execute([(int)$data['menu_id']]);
            $stock = $stockStmt->fetchColumn();

            if ($stock === false || ($stock !== null && (int)$stock <= 0)) {
                throw new RuntimeException('Stock indisponible.');
            }

            $stmt = $db->prepare("
                INSERT INTO commande (numero_commande, utilisateur_id, menu_id, date_prestation,
                    heure_livraison, adresse_livraison, ville_livraison, code_postal_livraison,
                    nombre_personne, prix_menu, prix_livraison, prix_total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['numero_commande'], $data['utilisateur_id'], $data['menu_id'],
                $data['date_prestation'], $data['heure_livraison'], $data['adresse_livraison'],
                $data['ville_livraison'], $data['code_postal_livraison'], $data['nombre_personne'],
                $data['prix_menu'], $data['prix_livraison'], $data['prix_total']
            ]);
            $id = (int)$db->lastInsertId();

            if ($stock !== null) {
                $db->prepare("UPDATE menu SET quantite_restante = quantite_restante - 1 WHERE menu_id = ?")
                   ->execute([(int)$data['menu_id']]);
            }

            self::addHistorique($id, null, commandeInitialStatus(), 'Commande passée', $data['utilisateur_id']);
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getByUser(int $userId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, m.titre AS menu_titre
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            WHERE c.utilisateur_id = ?
            ORDER BY c.date_commande DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, m.titre AS menu_titre, u.email, u.prenom, u.nom, u.telephone
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
            WHERE c.commande_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getAll(array $filters = []): array {
        $db  = Database::getConnection();
        $sql = "
            SELECT c.*, m.titre AS menu_titre, u.prenom, u.nom, u.email, u.telephone
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = ?"; $params[] = $filters['statut'];
        }
        if (!empty($filters['client'])) {
            $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
            $clientLike = '%' . $filters['client'] . '%';
            array_push($params, ...array_fill(0, 3, $clientLike));
        }
        $sql .= " ORDER BY c.date_commande DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
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
            ville_livraison=?, code_postal_livraison=?, nombre_personne=?,
            prix_menu=?, prix_livraison=?, prix_total=? WHERE commande_id=?
        ");
        $stmt->execute([
            $data['date_prestation'],
            $data['heure_livraison'],
            $data['adresse_livraison'],
            $data['ville_livraison'],
            $data['code_postal_livraison'],
            $data['nombre_personne'],
            $data['prix_menu'],
            $data['prix_livraison'],
            $data['prix_total'],
            $id,
        ]);
    }

    public static function cancel(int $id, string $motif, string $modeContact, int $modifiePar): void {
        $db   = Database::getConnection();
        $old  = self::getById($id);
        $db->prepare("UPDATE commande SET statut=?, motif_annulation=?, mode_contact_annulation=? WHERE commande_id=?")
           ->execute([commandeCancelledStatus(), $motif, $modeContact, $id]);
        self::addHistorique($id, $old['statut'] ?? null, commandeCancelledStatus(), "Annulation ($modeContact) : $motif", $modifiePar);
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

    public static function canModify(array $commande): bool {
        return commandeCanClientModify($commande);
    }

    public static function getStatsByMenu(): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT m.titre, COUNT(c.commande_id) AS nb_commandes, SUM(c.prix_total) AS ca_total
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            WHERE c.statut != ?
            GROUP BY c.menu_id
        ");
        $stmt->execute([commandeCancelledStatus()]);
        return $stmt->fetchAll();
    }

    public static function getCaStatsByMenu(int $menuId = 0, string $dateDebut = '', string $dateFin = ''): array {
        $sql = "
            SELECT m.menu_id, m.titre, SUM(c.prix_total) AS ca, COUNT(c.commande_id) AS nb
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            WHERE c.statut != ?
        ";
        $params = [commandeCancelledStatus()];

        if ($menuId) {
            $sql .= " AND c.menu_id = ?";
            $params[] = $menuId;
        }
        if ($dateDebut) {
            $sql .= " AND c.date_commande >= ?";
            $params[] = $dateDebut;
        }
        if ($dateFin) {
            $sql .= " AND c.date_commande <= ?";
            $params[] = $dateFin . ' 23:59:59';
        }

        $sql .= " GROUP BY c.menu_id ORDER BY ca DESC";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
