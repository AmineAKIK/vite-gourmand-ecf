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
     * Coût de revient d'une portion du plat (somme grammage × prix_unitaire).
     * grammage est dans l'unité de l'ingrédient (kg, L, pièce…).
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
     * Marges par plat pour tous les plats ayant au moins une ligne de recette.
     * Retourne : plat_id, titre, categorie, prix_vente_ht (null si pas de menu),
     *            cout_revient, marge_brute, taux_marge.
     */
    public static function margesParPlat(): array
    {
        $db = Database::getConnection();

        // Coût de revient par plat
        $couts = $db->query(
            'SELECT rl.plat_id,
                    COALESCE(SUM(rl.grammage * i.prix_unitaire), 0) AS cout_revient
             FROM recette_ligne rl
             JOIN ingredient i ON i.ingredient_id = rl.ingredient_id
             GROUP BY rl.plat_id'
        )->fetchAll(\PDO::FETCH_KEY_PAIR);   // [plat_id => cout_revient]

        if (!$couts) return [];

        $ids          = implode(',', array_map('intval', array_keys($couts)));
        $platsStmt    = $db->query(
            "SELECT p.plat_id, p.titre, cp.libelle AS categorie,
                    MIN(m.prix_par_personne) AS prix_vente_ttc
             FROM plat p
             JOIN categorie_plat cp ON cp.categorie_id = p.categorie_id
             LEFT JOIN menu_plat mp ON mp.plat_id = p.plat_id
             LEFT JOIN menu m       ON m.menu_id  = mp.menu_id AND m.actif = 1
             WHERE p.plat_id IN ($ids)
             GROUP BY p.plat_id, p.titre, cp.libelle"
        );

        $result = [];
        foreach ($platsStmt->fetchAll() as $row) {
            $cout       = round((float)($couts[$row['plat_id']] ?? 0), 4);
            $prix       = $row['prix_vente_ttc'] !== null ? (float)$row['prix_vente_ttc'] : null;
            $marge      = $prix !== null ? round($prix - $cout, 4) : null;
            $tauxMarge  = ($prix !== null && $prix > 0) ? round(($marge / $prix) * 100, 1) : null;
            $result[]   = [
                'plat_id'      => (int)$row['plat_id'],
                'titre'        => $row['titre'],
                'categorie'    => $row['categorie'],
                'cout_revient' => $cout,
                'prix_vente'   => $prix,
                'marge_brute'  => $marge,
                'taux_marge'   => $tauxMarge,
            ];
        }

        usort($result, fn($a, $b) => ($a['taux_marge'] ?? 999) <=> ($b['taux_marge'] ?? 999));
        return $result;
    }

    /** Sauvegarde toutes les lignes de recette d'un plat (remplace l'existant) */
    public static function syncLignes(int $platId, array $lignes): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM recette_ligne WHERE plat_id = ?')->execute([$platId]);
            if ($lignes) {
                $stmt = $db->prepare(
                    'INSERT INTO recette_ligne (plat_id, ingredient_id, grammage) VALUES (?, ?, ?)'
                );
                foreach ($lignes as $ligne) {
                    $grammage = (float)($ligne['grammage'] ?? 0);
                    if ($grammage > 0 && !empty($ligne['ingredient_id'])) {
                        $stmt->execute([$platId, (int)$ligne['ingredient_id'], $grammage]);
                    }
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
