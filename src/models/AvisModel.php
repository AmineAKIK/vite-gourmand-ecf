<?php
// src/models/AvisModel.php

class AvisModel
{
    public static function getPending(): array
    {
        return Database::getConnection()->query("
            SELECT a.*, u.prenom, u.nom,
                   GROUP_CONCAT(m.titre ORDER BY cl.ligne_id SEPARATOR ', ') AS menu_titre
            FROM avis a
            JOIN utilisateur u    ON u.utilisateur_id = a.utilisateur_id
            JOIN commande_ligne cl ON cl.commande_id  = a.commande_id
            JOIN menu m            ON m.menu_id       = cl.menu_id
            WHERE a.statut = 'en_attente'
            GROUP BY a.avis_id
            ORDER BY a.created_at ASC
        ")->fetchAll();
    }

    public static function getAll(?string $statut = null): array
    {
        $sql = "
            SELECT a.*, u.prenom, u.nom,
                   GROUP_CONCAT(m.titre ORDER BY cl.ligne_id SEPARATOR ', ') AS menu_titre
            FROM avis a
            JOIN utilisateur u    ON u.utilisateur_id = a.utilisateur_id
            JOIN commande_ligne cl ON cl.commande_id  = a.commande_id
            JOIN menu m            ON m.menu_id       = cl.menu_id
        ";
        if ($statut !== null) {
            $stmt = Database::getConnection()->prepare($sql . " WHERE a.statut = ? GROUP BY a.avis_id ORDER BY a.created_at DESC");
            $stmt->execute([$statut]);
        } else {
            $stmt = Database::getConnection()->query($sql . " GROUP BY a.avis_id ORDER BY a.created_at DESC");
        }
        return $stmt->fetchAll();
    }

    public static function getByCommande(int $commandeId): ?array
    {
        $stmt = Database::getConnection()->prepare("SELECT * FROM avis WHERE commande_id = ?");
        $stmt->execute([$commandeId]);
        return $stmt->fetch() ?: null;
    }

    /** Charge tous les avis pour un ensemble de commandes en une seule requête (évite N+1). */
    public static function getByCommandes(array $commandeIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $commandeIds))));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare("SELECT * FROM avis WHERE commande_id IN ($placeholders)");
        $stmt->execute($ids);
        $result = [];
        foreach ($stmt->fetchAll() as $avis) {
            $result[(int)$avis['commande_id']] = $avis;
        }
        return $result;
    }

    public static function getValidated(int $limit = 6): array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT a.*, u.prenom, u.nom,
                   GROUP_CONCAT(m.titre ORDER BY cl.ligne_id SEPARATOR ', ') AS menu_titre
            FROM avis a
            JOIN utilisateur u    ON u.utilisateur_id = a.utilisateur_id
            JOIN commande_ligne cl ON cl.commande_id  = a.commande_id
            JOIN menu m            ON m.menu_id       = cl.menu_id
            WHERE a.statut = 'valide'
            GROUP BY a.avis_id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function getHomepage(int $limit = 6): array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT a.*, u.prenom, u.nom,
                   GROUP_CONCAT(m.titre ORDER BY cl.ligne_id SEPARATOR ', ') AS menu_titre
            FROM avis a
            JOIN utilisateur u    ON u.utilisateur_id = a.utilisateur_id
            JOIN commande_ligne cl ON cl.commande_id  = a.commande_id
            JOIN menu m            ON m.menu_id       = cl.menu_id
            WHERE a.statut = 'valide'
              AND a.afficher_accueil = 1
            GROUP BY a.avis_id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $avis = $stmt->fetchAll();

        return $avis ?: self::getValidated($limit);
    }

    public static function getHomepageDuplicateClients(): array
    {
        return Database::getConnection()->query("
            SELECT a.utilisateur_id, u.prenom, u.nom, COUNT(*) AS total
            FROM avis a
            JOIN utilisateur u ON u.utilisateur_id = a.utilisateur_id
            WHERE a.statut = 'valide'
              AND a.afficher_accueil = 1
            GROUP BY a.utilisateur_id, u.prenom, u.nom
            HAVING COUNT(*) > 1
            ORDER BY total DESC, u.nom ASC, u.prenom ASC
        ")->fetchAll();
    }

    public static function setHomepageFeatured(int $avisId, bool $featured): bool
    {
        $stmt = Database::getConnection()->prepare("
            UPDATE avis
            SET afficher_accueil = ?
            WHERE avis_id = ?
              AND statut = 'valide'
        ");
        $stmt->execute([$featured ? 1 : 0, $avisId]);
        return $stmt->rowCount() > 0;
    }

    public static function existsForCommande(int $commandeId): bool
    {
        $stmt = Database::getConnection()->prepare("SELECT avis_id FROM avis WHERE commande_id = ?");
        $stmt->execute([$commandeId]);
        return (bool)$stmt->fetch();
    }

    public static function create(int $commandeId, int $userId, int $note, string $commentaire): void
    {
        Database::getConnection()
            ->prepare("INSERT INTO avis (commande_id, utilisateur_id, note, description) VALUES (?, ?, ?, ?)")
            ->execute([$commandeId, $userId, $note, $commentaire]);
    }

    public static function updateStatusByCommande(int $commandeId, string $status): void
    {
        $afficherAccueil = $status === 'valide' ? 'afficher_accueil' : '0';
        Database::getConnection()
            ->prepare("UPDATE avis SET statut = ?, afficher_accueil = $afficherAccueil WHERE commande_id = ?")
            ->execute([$status, $commandeId]);
    }

    public static function delete(int $avisId): void
    {
        Database::getConnection()
            ->prepare("DELETE FROM avis WHERE avis_id = ?")
            ->execute([$avisId]);
    }
}
