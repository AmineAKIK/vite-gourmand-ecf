<?php

namespace App\Models;

use App\Config\Database;

class RecetteModel
{
    /** Toutes les lignes de recette pour un plat, avec détail ingrédient */
    public static function getByPlat(int $platId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT rl.*, i.libelle, i.unite, i.prix_unitaire
             FROM recette_ligne rl
             JOIN ingredient i ON i.ingredient_id = rl.ingredient_id
             WHERE rl.plat_id = ?
             ORDER BY i.libelle'
        );
        $stmt->execute([$platId]);
        return $stmt->fetchAll();
    }

    /**
     * Coût matière d'une portion du plat (somme quantité × prix_unitaire).
     * La quantité est exprimée dans l'unité de l'ingrédient (kg, L, pièce…).
     */
    public static function coutRevient(int $platId): float
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COALESCE(SUM(rl.grammage * i.prix_unitaire), 0)
             FROM recette_ligne rl
             JOIN ingredient i ON i.ingredient_id = rl.ingredient_id
             WHERE rl.plat_id = ?'
        );
        $stmt->execute([$platId]);
        return round((float)$stmt->fetchColumn(), 4);
    }

    /**
     * Coûts matière par portion pour tous les plats ayant une fiche technique.
     *
     * Aucun prix de vente ni taux de marge n'est dérivé ici : le produit vend un
     * menu complet, pas chaque plat séparément. Attribuer le prix du menu à chaque
     * plat multiplierait artificiellement le chiffre d'affaires et la marge.
     */
    public static function coutsMatiereParPlat(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query(
            "SELECT
                p.plat_id,
                p.titre,
                cp.libelle AS categorie,
                ROUND(SUM(rl.grammage * i.prix_unitaire), 4) AS cout_matiere_portion,
                COUNT(DISTINCT CASE WHEN m.actif = 1 THEN m.menu_id END) AS nb_menus_actifs,
                GROUP_CONCAT(DISTINCT CASE WHEN m.actif = 1 THEN m.titre END ORDER BY m.titre SEPARATOR ', ') AS menus_actifs
             FROM plat p
             JOIN categorie_plat cp ON cp.categorie_id = p.categorie_id
             JOIN recette_ligne rl ON rl.plat_id = p.plat_id
             JOIN ingredient i ON i.ingredient_id = rl.ingredient_id
             LEFT JOIN menu_plat mp ON mp.plat_id = p.plat_id
             LEFT JOIN menu m ON m.menu_id = mp.menu_id
             GROUP BY p.plat_id, p.titre, cp.libelle
             ORDER BY cout_matiere_portion DESC, p.titre ASC"
        );

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[] = [
                'plat_id' => (int)$row['plat_id'],
                'titre' => (string)$row['titre'],
                'categorie' => (string)$row['categorie'],
                'cout_matiere_portion' => round((float)$row['cout_matiere_portion'], 4),
                'nb_menus_actifs' => (int)$row['nb_menus_actifs'],
                'menus_actifs' => (string)($row['menus_actifs'] ?? ''),
            ];
        }

        return $result;
    }
}
