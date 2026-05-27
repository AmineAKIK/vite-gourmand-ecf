<?php

namespace App\Models;

use App\Config\Database;

class UserModel {

    public static function findByEmail(string $email): ?array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.libelle AS role_libelle
            FROM utilisateur u
            JOIN role r ON r.role_id = u.role_id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.libelle AS role_libelle
            FROM utilisateur u
            JOIN role r ON r.role_id = u.role_id
            WHERE u.utilisateur_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO utilisateur (email, password, prenom, nom, telephone, adresse, ville, code_postal, role_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['email'], $data['password'], $data['prenom'], $data['nom'],
            $data['telephone'], $data['adresse'], $data['ville'], $data['code_postal'],
            ROLE_ID_USER
        ]);
        return (int)$db->lastInsertId();
    }

    public static function createEmploye(string $email, string $password, string $prenom, string $nom): int {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO utilisateur (email, password, prenom, nom, role_id, actif, must_change_password)
            VALUES (?, ?, ?, ?, ?, 1, 1)
        ");
        $stmt->execute([$email, $password, $prenom, $nom, ROLE_ID_EMPLOYE]);
        return (int)$db->lastInsertId();
    }

    public static function clearMustChangePassword(int $id): void {
        $db = Database::getConnection();
        $db->prepare("UPDATE utilisateur SET must_change_password=0 WHERE utilisateur_id=?")->execute([$id]);
    }

    public static function update(int $id, array $data): void {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE utilisateur SET prenom=?, nom=?, telephone=?, adresse=?, ville=?, code_postal=?
            WHERE utilisateur_id=?
        ");
        $stmt->execute([$data['prenom'], $data['nom'], $data['telephone'], $data['adresse'], $data['ville'], $data['code_postal'], $id]);
    }

    public static function updatePassword(int $id, string $hash): void {
        $db   = Database::getConnection();
        $db->prepare("UPDATE utilisateur SET password=? WHERE utilisateur_id=?")->execute([$hash, $id]);
    }

    public static function setActif(int $id, bool $actif): void {
        $db = Database::getConnection();
        $db->prepare("UPDATE utilisateur SET actif=? WHERE utilisateur_id=?")->execute([(int)$actif, $id]);
    }

    public static function getAllEmployes(): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM utilisateur WHERE role_id = ?");
        $stmt->execute([ROLE_ID_EMPLOYE]);
        return $stmt->fetchAll();
    }

    public static function saveResetToken(int $userId, string $token): void {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM password_reset WHERE utilisateur_id=?")->execute([$userId]);
        $db->prepare("INSERT INTO password_reset (utilisateur_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
           ->execute([$userId, $token]);
    }

    public static function findResetToken(string $token): ?array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM password_reset WHERE token=? AND expires_at > NOW() AND used=0");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function invalidateResetToken(string $token): void {
        $db = Database::getConnection();
        $db->prepare("UPDATE password_reset SET used=1 WHERE token=?")->execute([$token]);
    }

    public static function deleteEmploye(int $id): void {
        $db = Database::getConnection();
        // Détache l'employé de l'historique avant suppression (contrainte FK sans ON DELETE)
        $db->prepare("UPDATE commande_historique SET modifie_par = NULL WHERE modifie_par = ?")->execute([$id]);
        $db->prepare("DELETE FROM password_reset WHERE utilisateur_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM utilisateur WHERE utilisateur_id = ?")->execute([$id]);
    }

    public static function delete(int $id): void {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM password_reset WHERE utilisateur_id=?")->execute([$id]);

        $stmt = $db->prepare("SELECT 1 FROM commande WHERE utilisateur_id=? LIMIT 1");
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
            // Le client a des commandes : anonymise les données personnelles (contrainte FK + comptabilité)
            $db->prepare("
                UPDATE utilisateur
                SET email=?, password='*', prenom='Compte', nom='supprimé',
                    telephone=NULL, adresse=NULL, ville=NULL, code_postal=NULL, actif=0
                WHERE utilisateur_id=?
            ")->execute(['compte-supprime-' . $id . '@supprime.invalid', $id]);
        } else {
            $db->prepare("DELETE FROM utilisateur WHERE utilisateur_id=?")->execute([$id]);
        }
    }
}
