<?php

namespace App\Services;

use App\Config\Database;
use App\Config\SiteConfig;
use App\Geo\DeliveryResolver;
use InvalidArgumentException;
use Throwable;

class PricingService
{
    // ----------------------------------------------------------------
    // Calcul complet du total d'une commande à partir du panier session
    // ----------------------------------------------------------------

    /**
     * Calcule tous les montants d'une commande.
     *
     * $panierItems : tableau de la session, chaque item contient :
     *   menu_id, titre, prix_par_personne, minimum, nombre_personne
     *
     * $adresse, $ville, $codePostal : adresse de livraison saisie
     *
     * Retourne un array structuré contenant :
     *   - lignes[]        : une entrée par item panier avec tous les montants et snapshots
     *   - total_brut      : somme des prix (nb × prix/pers) avant remise
     *   - remise_globale  : montant total de remise (0 si sous le seuil)
     *   - total_menus_net : total menus après remise
     *   - prix_livraison  : frais de livraison calculés (null si adresse non reconnue)
     *   - total_ttc       : total_menus_net + prix_livraison
     *   - snapshots       : seuil, taux_reduction, taux_tva_menu, taux_tva_livraison, regime_tva
     *
     * Lève InvalidArgumentException si l'adresse n'est pas reconnue.
     */
    public static function computeOrderTotal(
        array $panierItems,
        string $adresse,
        string $ville,
        string $codePostal
    ): array {
        $tauxTvaMenu      = self::defaultTauxTvaByCategorie('menu');
        $tauxTvaLivraison = self::defaultTauxTvaByCategorie('livraison');
        $tauxTvaMenuId    = self::defaultTauxTvaIdByCategorie('menu');
        $tauxTvaLivraisonId = self::defaultTauxTvaIdByCategorie('livraison');
        $seuilReduction   = SiteConfig::discountThreshold();
        $tauxReduction    = SiteConfig::discountRate();
        $regimeTva        = self::regimeTva();

        // 1. Calculer le total brut (sans remise) sur tous les items
        $totalBrut = 0.0;
        foreach ($panierItems as $item) {
            $totalBrut += round((float)$item['prix_par_personne'] * (int)$item['nombre_personne'], 2);
        }
        $totalBrut = round($totalBrut, 2);

        // 2. Calculer la remise globale sur le total (pas par ligne)
        $remiseGlobale = 0.0;
        $tauxReductionApplique = 0.0;
        if ($seuilReduction > 0 && $totalBrut >= $seuilReduction) {
            $remiseGlobale = round($totalBrut * ($tauxReduction / 100), 2);
            $tauxReductionApplique = $tauxReduction;
        }
        $totalMenusNet = round($totalBrut - $remiseGlobale, 2);

        // 3. Répartir la remise proportionnellement sur chaque ligne
        $lignes = [];
        $remiseRepartie = 0.0;
        $nbItems = count($panierItems);
        foreach ($panierItems as $index => $item) {
            $prixBrutLigne = round((float)$item['prix_par_personne'] * (int)$item['nombre_personne'], 2);

            // Dernière ligne : prend le reste pour éviter les erreurs d'arrondi
            if ($index === $nbItems - 1) {
                $remiseLigne = round($remiseGlobale - $remiseRepartie, 2);
            } else {
                $remiseLigne = $totalBrut > 0
                    ? round($remiseGlobale * ($prixBrutLigne / $totalBrut), 2)
                    : 0.0;
                $remiseRepartie = round($remiseRepartie + $remiseLigne, 2);
            }

            $prixNetLigne = round($prixBrutLigne - $remiseLigne, 2);

            $lignes[] = [
                'menu_id'                    => (int)$item['menu_id'],
                'nombre_personne'            => (int)$item['nombre_personne'],
                'prix_par_personne_snapshot' => (float)$item['prix_par_personne'],
                'prix_menu'                  => $prixNetLigne,
                'remise_appliquee'           => $remiseLigne,
                'taux_tva_snapshot'          => $tauxTvaMenu,
                'taux_tva_id'                => $tauxTvaMenuId,
                'taux_reduction_snapshot'    => $tauxReductionApplique,
                // prix_livraison et prix_total_ligne : renseignés après calcul livraison
                'prix_livraison'             => 0.0,
                'prix_total_ligne'           => $prixNetLigne,
            ];
        }

        // 4. Calculer la livraison
        $prixLivraison = DeliveryResolver::computeDeliveryPrice($adresse, $ville, $codePostal);
        if ($prixLivraison === null) {
            throw new InvalidArgumentException(
                'Adresse de livraison non reconnue ou incohérente avec la ville et le code postal.'
            );
        }

        // 5. Porter la livraison sur la première ligne uniquement
        if (!empty($lignes)) {
            $lignes[0]['prix_livraison']  = $prixLivraison;
            $lignes[0]['prix_total_ligne'] = round($lignes[0]['prix_menu'] + $prixLivraison, 2);

            // Toutes les autres lignes : prix_total_ligne = prix_menu
            for ($i = 1; $i < count($lignes); $i++) {
                $lignes[$i]['prix_total_ligne'] = $lignes[$i]['prix_menu'];
            }
        }

        $totalTtc = round($totalMenusNet + $prixLivraison, 2);

        return [
            'lignes'          => $lignes,
            'total_brut'      => $totalBrut,
            'remise_globale'  => $remiseGlobale,
            'total_menus_net' => $totalMenusNet,
            'prix_livraison'  => $prixLivraison,
            'total_ttc'       => $totalTtc,
            'snapshots'       => [
                'seuil_reduction'       => $seuilReduction,
                'taux_reduction'        => $tauxReductionApplique,
                'taux_tva_menu'         => $tauxTvaMenu,
                'taux_tva_menu_id'      => $tauxTvaMenuId,
                'taux_tva_livraison'    => $tauxTvaLivraison,
                'taux_tva_livraison_id' => $tauxTvaLivraisonId,
                'regime_tva'            => $regimeTva,
            ],
        ];
    }

    // ----------------------------------------------------------------
    // Validation des prix du panier avant checkout
    // ----------------------------------------------------------------

    /**
     * Vérifie que les prix_par_personne du panier session correspondent
     * aux prix actuels en base. Retourne la liste des items dont le prix
     * a changé (tableau vide = tout est cohérent).
     *
     * $panierItems : tableau de la session
     * Retourne [] si tout est OK, ou [{menu_id, titre, prix_session, prix_actuel}]
     */
    public static function detectPrixChanges(array $panierItems): array
    {
        if (empty($panierItems)) {
            return [];
        }

        $menuIds = array_unique(array_column($panierItems, 'menu_id'));
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT menu_id, titre, prix_par_personne FROM menu WHERE menu_id IN ($placeholders)"
        );
        $stmt->execute(array_values($menuIds));

        $prixActuels = [];
        foreach ($stmt->fetchAll() as $row) {
            $prixActuels[(int)$row['menu_id']] = [
                'titre'             => $row['titre'],
                'prix_par_personne' => (float)$row['prix_par_personne'],
            ];
        }

        $changes = [];
        foreach ($panierItems as $item) {
            $menuId = (int)$item['menu_id'];
            if (!isset($prixActuels[$menuId])) {
                continue;
            }
            $prixSession = (float)$item['prix_par_personne'];
            $prixActuel  = $prixActuels[$menuId]['prix_par_personne'];
            if (abs($prixSession - $prixActuel) > 0.001) {
                $changes[] = [
                    'menu_id'      => $menuId,
                    'titre'        => $prixActuels[$menuId]['titre'],
                    'prix_session' => $prixSession,
                    'prix_actuel'  => $prixActuel,
                ];
            }
        }

        return $changes;
    }

    // ----------------------------------------------------------------
    // Conversions HT / TTC
    // ----------------------------------------------------------------

    public static function htFromTtc(float $ttc, float $tauxTva): float
    {
        if ($tauxTva <= 0) {
            return round($ttc, 2);
        }
        return round($ttc / (1 + $tauxTva / 100), 2);
    }

    public static function ttcFromHt(float $ht, float $tauxTva): float
    {
        return round($ht * (1 + $tauxTva / 100), 2);
    }

    public static function tvaFromTtc(float $ttc, float $tauxTva): float
    {
        $ht = self::htFromTtc($ttc, $tauxTva);
        return round($ttc - $ht, 2);
    }

    // ----------------------------------------------------------------
    // Lecture du régime TVA et des taux depuis la DB
    // ----------------------------------------------------------------

    public static function regimeTva(): string
    {
        $regime = SiteConfig::get('regime_tva', 'assujetti');
        return in_array($regime, ['assujetti', 'non_assujetti'], true) ? $regime : 'assujetti';
    }

    public static function isAssujetti(): bool
    {
        return self::regimeTva() === 'assujetti';
    }

    /**
     * Taux TVA par défaut pour une catégorie ('menu', 'livraison', 'general').
     * Lit depuis la table taux_tva (migration 015).
     * Fallback sur 10% si la table n'existe pas encore.
     */
    public static function defaultTauxTvaByCategorie(string $categorie): float
    {
        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT taux FROM taux_tva WHERE categorie = ? AND par_defaut = 1 AND actif = 1 LIMIT 1"
            );
            $stmt->execute([$categorie]);
            $taux = $stmt->fetchColumn();
            if ($taux !== false) {
                return (float)$taux;
            }
        } catch (Throwable) {
            // table taux_tva pas encore créée (avant migration 015)
        }

        // Fallback : régime non assujetti → 0%, sinon 10%
        return self::isAssujetti() ? 10.0 : 0.0;
    }

    public static function defaultTauxTvaIdByCategorie(string $categorie): ?int
    {
        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT taux_id FROM taux_tva WHERE categorie = ? AND par_defaut = 1 AND actif = 1 LIMIT 1"
            );
            $stmt->execute([$categorie]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int)$id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Liste tous les taux actifs, pour les menus déroulants dans l'interface de facturation.
     */
    public static function tauxTvaActifs(): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT taux_id, libelle, taux, categorie, par_defaut FROM taux_tva WHERE actif = 1 ORDER BY taux ASC, libelle ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [
                ['taux_id' => null, 'libelle' => 'Restauration traiteur – 10%', 'taux' => 10.0, 'categorie' => 'menu', 'par_defaut' => 1],
            ];
        }
    }
}
