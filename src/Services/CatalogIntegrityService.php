<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\CatalogIntegrityPolicy;
use PDO;
use RuntimeException;
use Throwable;

final class CatalogIntegrityService
{
    public static function createMenu(array $data, array $platIds, array $imagePaths): int
    {
        CatalogIntegrityPolicy::assertMenuPayload($data);
        $platIds = CatalogIntegrityPolicy::ids($platIds);
        if ($imagePaths === []) {
            throw new RuntimeException('Au moins une photo valide est obligatoire.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::assertReferences($db, $data, $platIds);
            $stmt = $db->prepare(
                'INSERT INTO menu (titre, description, nombre_personne_minimum, prix_par_personne, quantite_restante, conditions, theme_id, regime_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $stmt->execute([
                $data['titre'],
                $data['description'],
                $data['nombre_personne_minimum'],
                $data['prix_par_personne'],
                $data['quantite_restante'],
                $data['conditions'],
                $data['theme_id'],
                $data['regime_id'],
            ]);
            $menuId = (int) $db->lastInsertId();
            self::replaceMenuPlats($db, $menuId, $platIds);
            self::appendImages($db, $menuId, $imagePaths, 1);
            $db->commit();
            return $menuId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function updateMenu(int $menuId, array $data, array $platIds, array $newImagePaths): void
    {
        CatalogIntegrityPolicy::assertMenuPayload($data);
        $platIds = CatalogIntegrityPolicy::ids($platIds);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::lockMenu($db, $menuId);
            self::assertReferences($db, $data, $platIds);
            $stmt = $db->prepare(
                'UPDATE menu SET titre=?, description=?, nombre_personne_minimum=?, prix_par_personne=?, quantite_restante=?, conditions=?, theme_id=?, regime_id=? WHERE menu_id=?',
            );
            $stmt->execute([
                $data['titre'],
                $data['description'],
                $data['nombre_personne_minimum'],
                $data['prix_par_personne'],
                $data['quantite_restante'],
                $data['conditions'],
                $data['theme_id'],
                $data['regime_id'],
                $menuId,
            ]);
            self::replaceMenuPlats($db, $menuId, $platIds);
            if ($newImagePaths !== []) {
                $orderStmt = $db->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 FROM menu_image WHERE menu_id = ?');
                $orderStmt->execute([$menuId]);
                self::appendImages($db, $menuId, $newImagePaths, (int) $orderStmt->fetchColumn());
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deactivateMenu(int $menuId): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::lockMenu($db, $menuId);
            $db->prepare('UPDATE menu SET actif = 0 WHERE menu_id = ?')->execute([$menuId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function createPlat(array $data): int
    {
        CatalogIntegrityPolicy::assertPlatPayload($data);
        $allergenIds = CatalogIntegrityPolicy::ids((array) ($data['allergen_ids'] ?? []));

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::assertIdsExist($db, 'categorie_plat', 'categorie_id', [(int) $data['categorie_id']], 'Catégorie introuvable.');
            self::assertIdsExist($db, 'allergen', 'allergen_id', $allergenIds, 'Allergène introuvable.');
            $stmt = $db->prepare('INSERT INTO plat (titre, categorie_id) VALUES (?, ?)');
            $stmt->execute([$data['titre'], $data['categorie_id']]);
            $platId = (int) $db->lastInsertId();
            self::replacePlatAllergens($db, $platId, $allergenIds);
            $db->commit();
            return $platId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function updatePlat(int $platId, array $data): void
    {
        CatalogIntegrityPolicy::assertPlatPayload($data);
        $allergenIds = CatalogIntegrityPolicy::ids((array) ($data['allergen_ids'] ?? []));

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::lockPlat($db, $platId);
            self::assertIdsExist($db, 'categorie_plat', 'categorie_id', [(int) $data['categorie_id']], 'Catégorie introuvable.');
            self::assertIdsExist($db, 'allergen', 'allergen_id', $allergenIds, 'Allergène introuvable.');
            $db->prepare('UPDATE plat SET titre = ?, categorie_id = ? WHERE plat_id = ?')
                ->execute([$data['titre'], $data['categorie_id'], $platId]);
            self::replacePlatAllergens($db, $platId, $allergenIds);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deletePlat(int $platId): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::lockPlat($db, $platId);
            $usage = $db->prepare('SELECT COUNT(*) FROM menu_plat WHERE plat_id = ?');
            $usage->execute([$platId]);
            if ((int) $usage->fetchColumn() > 0) {
                throw new RuntimeException('Impossible de supprimer un plat utilisé dans un menu. Retirez-le d’abord des menus concernés.');
            }
            $db->prepare('DELETE FROM plat WHERE plat_id = ?')->execute([$platId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{path:string,menu_id:int}|null */
    public static function detachImage(int $imageId): ?array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT image_id, menu_id, chemin FROM menu_image WHERE image_id = ? FOR UPDATE');
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();
            if (!$image) {
                $db->commit();
                return null;
            }
            self::lockMenu($db, (int) $image['menu_id']);
            $count = $db->prepare('SELECT COUNT(*) FROM menu_image WHERE menu_id = ?');
            $count->execute([(int) $image['menu_id']]);
            if ((int) $count->fetchColumn() <= 1) {
                throw new RuntimeException('Un menu actif doit conserver au moins une image.');
            }
            $db->prepare('DELETE FROM menu_image WHERE image_id = ?')->execute([$imageId]);
            $db->commit();
            return ['path' => (string) $image['chemin'], 'menu_id' => (int) $image['menu_id']];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function assertReferences(PDO $db, array $data, array $platIds): void
    {
        if (($data['theme_id'] ?? null) !== null) {
            self::assertIdsExist($db, 'theme', 'theme_id', [(int) $data['theme_id']], 'Thème introuvable.');
        }
        if (($data['regime_id'] ?? null) !== null) {
            self::assertIdsExist($db, 'regime', 'regime_id', [(int) $data['regime_id']], 'Régime introuvable.');
        }
        self::assertIdsExist($db, 'plat', 'plat_id', $platIds, 'Un plat sélectionné est introuvable.');
    }

    private static function assertIdsExist(PDO $db, string $table, string $column, array $ids, string $message): void
    {
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE {$column} IN ({$placeholders}) FOR UPDATE");
        $stmt->execute($ids);
        $found = array_map('intval', array_column($stmt->fetchAll(), $column));
        sort($found);
        $expected = array_values(array_unique(array_map('intval', $ids)));
        sort($expected);
        if ($found !== $expected) {
            throw new RuntimeException($message);
        }
    }

    private static function lockMenu(PDO $db, int $menuId): void
    {
        $stmt = $db->prepare('SELECT menu_id FROM menu WHERE menu_id = ? AND actif = 1 FOR UPDATE');
        $stmt->execute([$menuId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Menu introuvable.');
        }
    }

    private static function lockPlat(PDO $db, int $platId): void
    {
        $stmt = $db->prepare('SELECT plat_id FROM plat WHERE plat_id = ? FOR UPDATE');
        $stmt->execute([$platId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Plat introuvable.');
        }
    }

    private static function replaceMenuPlats(PDO $db, int $menuId, array $platIds): void
    {
        $db->prepare('DELETE FROM menu_plat WHERE menu_id = ?')->execute([$menuId]);
        if ($platIds === []) {
            return;
        }
        $insert = $db->prepare('INSERT INTO menu_plat (menu_id, plat_id) VALUES (?, ?)');
        foreach ($platIds as $platId) {
            $insert->execute([$menuId, $platId]);
        }
    }

    private static function replacePlatAllergens(PDO $db, int $platId, array $allergenIds): void
    {
        $db->prepare('DELETE FROM plat_allergen WHERE plat_id = ?')->execute([$platId]);
        if ($allergenIds === []) {
            return;
        }
        $insert = $db->prepare('INSERT INTO plat_allergen (plat_id, allergen_id) VALUES (?, ?)');
        foreach ($allergenIds as $allergenId) {
            $insert->execute([$platId, $allergenId]);
        }
    }

    private static function appendImages(PDO $db, int $menuId, array $imagePaths, int $startOrder): void
    {
        $insert = $db->prepare('INSERT INTO menu_image (menu_id, chemin, ordre) VALUES (?, ?, ?)');
        $order = $startOrder;
        foreach ($imagePaths as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                throw new RuntimeException('Chemin d’image invalide.');
            }
            $insert->execute([$menuId, $path, $order++]);
        }
    }
}
