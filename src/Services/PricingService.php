<?php

namespace App\Services;

use App\Config\Configuration;
use App\Config\ConfigurationCompleteness;
use App\Config\Database;
use App\Domain\Money;
use App\Domain\OrderPricingCalculator;
use App\Geo\DeliveryResolver;
use InvalidArgumentException;
use RuntimeException;

class PricingService
{
    /**
     * Calcule tous les montants d'une commande.
     *
     * Le contrat financier canonique est exprimé en centimes entiers. Les champs
     * historiques en euros restent présents pour compatibilité avec la base et les vues.
     */
    public static function computeOrderTotal(
        array $panierItems,
        string $adresse,
        string $ville,
        string $codePostal
    ): array {
        ConfigurationCompleteness::assertOrderingReady();

        $tauxTvaMenu = self::defaultTauxTvaByCategorie('menu');
        $tauxTvaLivraison = self::defaultTauxTvaByCategorie('livraison');
        $tauxTvaMenuId = self::defaultTauxTvaIdByCategorie('menu');
        $tauxTvaLivraisonId = self::defaultTauxTvaIdByCategorie('livraison');
        $seuilReduction = Configuration::get('discount.threshold');
        $tauxReduction = Configuration::get('discount.rate_percent');
        if (!is_string($seuilReduction) || !is_int($tauxReduction)) {
            throw new RuntimeException('configuration_incomplete:pricing');
        }
        $discountThresholdCents = Money::fromDecimal($seuilReduction);
        $discountRateBasisPoints = $tauxReduction * 100;
        $regimeTva = self::regimeTva();

        $prixLivraison = DeliveryResolver::computeDeliveryPrice($adresse, $ville, $codePostal);
        if ($prixLivraison === null) {
            throw new InvalidArgumentException(
                'Adresse de livraison non reconnue ou incohérente avec la ville et le code postal.'
            );
        }

        $pricing = OrderPricingCalculator::calculate(
            $panierItems,
            Money::fromDecimal($prixLivraison),
            $discountThresholdCents,
            $discountRateBasisPoints
        );

        $lignes = [];
        foreach ($pricing['lignes'] as $index => $line) {
            $prixLivraisonCents = $index === 0 ? $pricing['prix_livraison_cents'] : 0;
            $prixTotalLigneCents = $line['prix_menu_net_cents'] + $prixLivraisonCents;

            $lignes[] = [
                'menu_id' => $line['menu_id'],
                'nombre_personne' => $line['nombre_personne'],
                'prix_par_personne_cents' => $line['prix_par_personne_cents'],
                'prix_menu_brut_cents' => $line['prix_menu_brut_cents'],
                'prix_menu_net_cents' => $line['prix_menu_net_cents'],
                'remise_appliquee_cents' => $line['remise_appliquee_cents'],
                'taux_tva_basis_points' => $tauxTvaMenu,
                'taux_tva_id' => $tauxTvaMenuId,
                'taux_reduction_basis_points' => $pricing['taux_reduction_basis_points'],
                'prix_livraison_cents' => $prixLivraisonCents,
                'prix_total_ligne_cents' => $prixTotalLigneCents,
            ];
        }

        return [
            'lignes' => $lignes,
            'total_brut_cents' => $pricing['total_brut_cents'],
            'remise_globale_cents' => $pricing['remise_globale_cents'],
            'total_menus_net_cents' => $pricing['total_menus_net_cents'],
            'prix_livraison_cents' => $pricing['prix_livraison_cents'],
            'total_ttc_cents' => $pricing['total_ttc_cents'],
            'currency' => (string) Configuration::get('market.currency'),
            'snapshots' => [
                'seuil_reduction_cents' => $discountThresholdCents,
                'taux_reduction_basis_points' => $pricing['taux_reduction_basis_points'],
                'taux_tva_menu' => $tauxTvaMenu,
                'taux_tva_menu_id' => $tauxTvaMenuId,
                'taux_tva_livraison' => $tauxTvaLivraison,
                'taux_tva_livraison_id' => $tauxTvaLivraisonId,
                'regime_tva' => $regimeTva,
            ],
        ];
    }

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
            $prixActuels[(int) $row['menu_id']] = [
                'titre' => $row['titre'],
                'prix_par_personne' => (string) $row['prix_par_personne'],
            ];
        }

        $changes = [];
        foreach ($panierItems as $item) {
            $menuId = (int) $item['menu_id'];
            if (!isset($prixActuels[$menuId])) {
                continue;
            }
            $prixSession = (string) $item['prix_par_personne'];
            $prixActuel = (string) $prixActuels[$menuId]['prix_par_personne'];
            if (Money::fromDecimal($prixSession) !== Money::fromDecimal($prixActuel)) {
                $changes[] = [
                    'menu_id' => $menuId,
                    'titre' => $prixActuels[$menuId]['titre'],
                    'prix_session' => $prixSession,
                    'prix_actuel' => $prixActuel,
                ];
            }
        }

        return $changes;
    }

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

    public static function regimeTva(): string
    {
        $regime = Configuration::get('tax.regime');
        if (!is_string($regime)) {
            throw new RuntimeException('configuration_incomplete:billing:tax.regime');
        }

        return $regime;
    }

    public static function isAssujetti(): bool
    {
        return self::regimeTva() === 'assujetti';
    }

    public static function defaultTauxTvaByCategorie(string $categorie): float
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT taux FROM taux_tva WHERE categorie = ? AND par_defaut = 1 AND actif = 1 LIMIT 1'
        );
        $stmt->execute([$categorie]);
        $taux = $stmt->fetchColumn();
        if ($taux === false) {
            throw new RuntimeException('configuration_incomplete:tax_rate:' . $categorie);
        }

        return (float) $taux;
    }

    public static function defaultTauxTvaIdByCategorie(string $categorie): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT taux_id FROM taux_tva WHERE categorie = ? AND par_defaut = 1 AND actif = 1 LIMIT 1'
        );
        $stmt->execute([$categorie]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('configuration_incomplete:tax_rate:' . $categorie);
        }

        return (int) $id;
    }

    public static function tauxTvaActifs(): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT taux_id, libelle, taux, categorie, par_defaut FROM taux_tva WHERE actif = 1 ORDER BY taux ASC, libelle ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
