<?php

namespace App\Models;

use App\Config\Database;

class NotificationModel
{
    public static function ensureTable(): void
    {
        try {
            Database::getConnection()->exec("
                CREATE TABLE IF NOT EXISTS notification (
                    notification_id INT AUTO_INCREMENT PRIMARY KEY,
                    utilisateur_id  INT NOT NULL,
                    type            VARCHAR(50)  NOT NULL,
                    titre           VARCHAR(255) NOT NULL,
                    corps           TEXT         NULL,
                    lu              TINYINT(1)   NOT NULL DEFAULT 0,
                    commande_id     INT          NULL DEFAULT NULL,
                    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_notif_user_lu (utilisateur_id, lu),
                    INDEX idx_notif_commande (commande_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable) {}
    }

    public static function create(array $data): void
    {
        try {
            self::ensureTable();
            Database::getConnection()->prepare(
                "INSERT INTO notification (utilisateur_id, type, titre, corps, commande_id)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([
                (int)$data['utilisateur_id'],
                $data['type'],
                $data['titre'],
                $data['corps'] ?? null,
                isset($data['commande_id']) ? (int)$data['commande_id'] : null,
            ]);
        } catch (\Throwable) {}
    }

    public static function getUnread(int $userId, int $limit = 20): array
    {
        try {
            self::ensureTable();
            $stmt = Database::getConnection()->prepare(
                "SELECT * FROM notification WHERE utilisateur_id = ? AND lu = 0
                 ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function getAll(int $userId, int $limit = 50): array
    {
        try {
            self::ensureTable();
            $stmt = Database::getConnection()->prepare(
                "SELECT * FROM notification WHERE utilisateur_id = ?
                 ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function countUnread(int $userId): int
    {
        try {
            self::ensureTable();
            $stmt = Database::getConnection()->prepare(
                "SELECT COUNT(*) FROM notification WHERE utilisateur_id = ? AND lu = 0"
            );
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        try {
            Database::getConnection()->prepare(
                "UPDATE notification SET lu = 1 WHERE notification_id = ? AND utilisateur_id = ?"
            )->execute([$notificationId, $userId]);
        } catch (\Throwable) {}
    }

    public static function markAllRead(int $userId): void
    {
        try {
            Database::getConnection()->prepare(
                "UPDATE notification SET lu = 1 WHERE utilisateur_id = ?"
            )->execute([$userId]);
        } catch (\Throwable) {}
    }

    /**
     * Envoie une notification "nouvelle commande" à tous les employés et admins.
     */
    public static function notifyEmployesNouvelleCommande(int $commandeId, string $numeroCommande, string $clientNom): void
    {
        try {
            self::ensureTable();
            $db    = Database::getConnection();
            $stmt  = $db->prepare(
                "SELECT utilisateur_id FROM utilisateur
                 WHERE role_id IN (?, ?) AND actif = 1"
            );
            $stmt->execute([ROLE_ID_EMPLOYE, ROLE_ID_ADMIN]);
            $employes = $stmt->fetchAll();
            foreach ($employes as $emp) {
                self::create([
                    'utilisateur_id' => (int)$emp['utilisateur_id'],
                    'type'           => 'nouvelle_commande',
                    'titre'          => 'Nouvelle commande #' . $numeroCommande,
                    'corps'          => 'Client : ' . $clientNom,
                    'commande_id'    => $commandeId,
                ]);
            }
        } catch (\Throwable) {}
    }

    /**
     * Envoie une notification de changement de statut au client de la commande.
     */
    public static function notifyClientStatutCommande(int $commandeId, int $clientId, string $numeroCommande, string $nouveauStatut): void
    {
        try {
            $labels = [
                'accepte'             => 'votre commande a été acceptée',
                'en_preparation'      => 'votre commande est en préparation',
                'en_cours_livraison'  => 'votre commande est en cours de livraison',
                'livre'               => 'votre commande a été livrée',
                'en_attente_materiel' => 'retour du matériel en attente',
                'terminee'            => 'votre commande est terminée',
                'annulee'             => 'votre commande a été annulée',
            ];
            $label = $labels[$nouveauStatut] ?? 'statut mis à jour';
            self::create([
                'utilisateur_id' => $clientId,
                'type'           => 'statut_commande',
                'titre'          => 'Commande #' . $numeroCommande . ' — ' . ucfirst($label),
                'corps'          => null,
                'commande_id'    => $commandeId,
            ]);
        } catch (\Throwable) {}
    }
}
