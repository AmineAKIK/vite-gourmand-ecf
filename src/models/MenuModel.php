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

    public static function getImagesByMenuIds(array $menuIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $menuIds))));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare("
            SELECT * FROM menu_image
            WHERE menu_id IN ($placeholders)
            ORDER BY menu_id, ordre
        ");
        $stmt->execute($ids);

        $images = [];
        foreach ($stmt->fetchAll() as $image) {
            $images[(int)$image['menu_id']][] = $image;
        }
        return $images;
    }

    public static function getPlats(int $menuId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT p.plat_id, p.titre, p.allergenes, p.categorie_id, cp.libelle AS categorie
            FROM plat p
            JOIN menu_plat mp ON mp.plat_id = p.plat_id
            JOIN categorie_plat cp ON cp.categorie_id = p.categorie_id
            WHERE mp.menu_id = ?
            ORDER BY cp.libelle, p.titre
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

    public static function getPlatsForAdmin(): array {
        return Database::getConnection()->query("
            SELECT p.plat_id, p.titre, p.allergenes, p.categorie_id, cp.libelle AS categorie
            FROM plat p
            JOIN categorie_plat cp ON cp.categorie_id = p.categorie_id
            ORDER BY cp.libelle, p.titre
        ")->fetchAll();
    }

    public static function getCategories(): array {
        return Database::getConnection()->query("SELECT * FROM categorie_plat ORDER BY libelle")->fetchAll();
    }

    public static function getPlatsByMenu(): array {
        $platsByMenu = [];
        $rows = Database::getConnection()->query("SELECT menu_id, plat_id FROM menu_plat")->fetchAll();
        foreach ($rows as $row) {
            $platsByMenu[(int)$row['menu_id']][] = (int)$row['plat_id'];
        }
        return $platsByMenu;
    }

    public static function replaceMenuPlats(int $menuId, array $platIds): void {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM menu_plat WHERE menu_id = ?")->execute([$menuId]);

        if (empty($platIds)) {
            return;
        }

        self::insertMenuPlats($db, $menuId, $platIds);
    }

    public static function addMenuPlats(int $menuId, array $platIds): void {
        if (empty($platIds)) {
            return;
        }

        self::insertMenuPlats(Database::getConnection(), $menuId, $platIds);
    }

    public static function nextMenuImageOrder(int $menuId): int {
        $stmt = Database::getConnection()->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM menu_image WHERE menu_id=?");
        $stmt->execute([$menuId]);
        return (int)$stmt->fetchColumn();
    }

    public static function addMenuImage(int $menuId, string $path, int $order): void {
        Database::getConnection()
            ->prepare("INSERT INTO menu_image (menu_id, chemin, ordre) VALUES (?,?,?)")
            ->execute([$menuId, $path, $order]);
    }

    public static function getMenuImagePath(int $imageId): ?string {
        $stmt = Database::getConnection()->prepare("SELECT chemin FROM menu_image WHERE image_id=?");
        $stmt->execute([$imageId]);
        $path = $stmt->fetchColumn();
        return $path !== false ? (string)$path : null;
    }

    public static function deleteMenuImage(int $imageId): void {
        Database::getConnection()
            ->prepare("DELETE FROM menu_image WHERE image_id=?")
            ->execute([$imageId]);
    }

    public static function createPlat(array $data): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO plat (titre, categorie_id, allergenes) VALUES (?, ?, ?)");
        $stmt->execute([$data['titre'], $data['categorie_id'], $data['allergenes'] ?? '']);
        return (int)$db->lastInsertId();
    }

    public static function updatePlat(int $id, array $data): void {
        Database::getConnection()
            ->prepare("UPDATE plat SET titre=?, categorie_id=?, allergenes=? WHERE plat_id=?")
            ->execute([$data['titre'], $data['categorie_id'], $data['allergenes'] ?? '', $id]);
    }

    public static function platIsUsed(int $platId): bool {
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM menu_plat WHERE plat_id = ?");
        $stmt->execute([$platId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function deletePlat(int $platId): void {
        Database::getConnection()
            ->prepare("DELETE FROM plat WHERE plat_id = ?")
            ->execute([$platId]);
    }

    private static function insertMenuPlats(PDO $db, int $menuId, array $platIds): void {
        $stmt = $db->prepare("INSERT IGNORE INTO menu_plat (menu_id, plat_id) VALUES (?, ?)");
        foreach ($platIds as $platId) {
            $stmt->execute([$menuId, $platId]);
        }
    }

}
