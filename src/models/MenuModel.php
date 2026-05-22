<?php
// src/models/MenuModel.php

class MenuModel {

    public static function getAll(array $filters = []): array {
        $db  = Database::getConnection();
        $sql = "
            SELECT m.*, t.libelle AS theme, r.libelle AS regime,
                   (SELECT chemin FROM menu_image WHERE menu_id=m.menu_id ORDER BY ordre LIMIT 1) AS image_principale
            FROM menu m
            LEFT JOIN theme t ON t.theme_id = m.theme_id
            LEFT JOIN regime r ON r.regime_id = m.regime_id
            WHERE m.actif = 1
        ";
        $params = [];

        if (!empty($filters['prix_max'])) {
            $sql .= " AND m.prix_par_personne * m.nombre_personne_minimum <= ?";
            $params[] = (float)$filters['prix_max'];
        }
        if (!empty($filters['prix_min'])) {
            $sql .= " AND m.prix_par_personne * m.nombre_personne_minimum >= ?";
            $params[] = (float)$filters['prix_min'];
        }
        if (!empty($filters['theme_id'])) {
            $sql .= " AND m.theme_id = ?";
            $params[] = (int)$filters['theme_id'];
        }
        if (!empty($filters['regime_id'])) {
            $sql .= " AND m.regime_id = ?";
            $params[] = (int)$filters['regime_id'];
        }
        if (!empty($filters['nb_personnes'])) {
            $sql .= " AND m.nombre_personne_minimum <= ?";
            $params[] = (int)$filters['nb_personnes'];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT m.*, t.libelle AS theme, r.libelle AS regime
            FROM menu m
            LEFT JOIN theme t ON t.theme_id = m.theme_id
            LEFT JOIN regime r ON r.regime_id = m.regime_id
            WHERE m.menu_id = ? AND m.actif = 1
        ");
        $stmt->execute([$id]);
        $menu = $stmt->fetch();
        if (!$menu) return null;

        $menu['images'] = self::getImages($id);
        $menu['plats']  = self::getPlats($id);
        return $menu;
    }

    public static function getImages(int $menuId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM menu_image WHERE menu_id=? ORDER BY ordre");
        $stmt->execute([$menuId]);
        return $stmt->fetchAll();
    }

    public static function getPlats(int $menuId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT p.*, cp.libelle AS categorie,
                   GROUP_CONCAT(a.libelle SEPARATOR ', ') AS allergenes
            FROM plat p
            JOIN menu_plat mp ON mp.plat_id = p.plat_id
            JOIN categorie_plat cp ON cp.categorie_id = p.categorie_id
            LEFT JOIN plat_allergene pa ON pa.plat_id = p.plat_id
            LEFT JOIN allergene a ON a.allergene_id = pa.allergene_id
            WHERE mp.menu_id = ?
            GROUP BY p.plat_id, p.titre, p.description, p.categorie_id, p.photo_chemin, cp.libelle
        ");
        $stmt->execute([$menuId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO menu (titre, description, nombre_personne_minimum, prix_par_personne,
                              quantite_restante, conditions, theme_id, regime_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['titre'], $data['description'], $data['nombre_personne_minimum'],
            $data['prix_par_personne'], $data['quantite_restante'],
            $data['conditions'], $data['theme_id'] ?: null, $data['regime_id'] ?: null
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE menu SET titre=?, description=?, nombre_personne_minimum=?,
            prix_par_personne=?, quantite_restante=?, conditions=?, theme_id=?, regime_id=?
            WHERE menu_id=?
        ");
        $stmt->execute([
            $data['titre'], $data['description'], $data['nombre_personne_minimum'],
            $data['prix_par_personne'], $data['quantite_restante'],
            $data['conditions'], $data['theme_id'] ?: null, $data['regime_id'] ?: null, $id
        ]);
    }

    public static function delete(int $id): void {
        $db = Database::getConnection();
        $db->prepare("UPDATE menu SET actif=0 WHERE menu_id=?")->execute([$id]);
    }

    public static function getThemes(): array {
        return Database::getConnection()->query("SELECT * FROM theme")->fetchAll();
    }

    public static function getRegimes(): array {
        return Database::getConnection()->query("SELECT * FROM regime")->fetchAll();
    }

    public static function decrementStock(int $menuId): void {
        $db = Database::getConnection();
        $db->prepare("UPDATE menu SET quantite_restante = quantite_restante - 1 WHERE menu_id=? AND quantite_restante > 0")->execute([$menuId]);
    }
}
