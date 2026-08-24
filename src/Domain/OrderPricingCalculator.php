<?php

namespace App\Domain;

final class OrderPricingCalculator
{
    /**
     * @param array<int, array{menu_id:int|string, prix_par_personne:float|int|string, nombre_personne:int|string}> $items
     * @return array{
     *   lignes: array<int, array<string, int|float>>,
     *   total_brut_cents:int,
     *   remise_globale_cents:int,
     *   total_menus_net_cents:int,
     *   prix_livraison_cents:int,
     *   total_ttc_cents:int,
     *   taux_reduction_applique:float
     * }
     */
    public static function calculate(
        array $items,
        int $deliveryCents,
        int $discountThresholdCents,
        float $discountRate
    ): array {
        $grossLines = [];
        $totalGrossCents = 0;

        foreach ($items as $item) {
            $unitCents = Money::fromDecimal($item['prix_par_personne']);
            $quantity = max(0, (int) $item['nombre_personne']);
            $lineGrossCents = $unitCents * $quantity;
            $totalGrossCents += $lineGrossCents;

            $grossLines[] = [
                'menu_id' => (int) $item['menu_id'],
                'nombre_personne' => $quantity,
                'prix_par_personne_cents' => $unitCents,
                'prix_menu_brut_cents' => $lineGrossCents,
            ];
        }

        $appliedRate = 0.0;
        $discountCents = 0;
        if ($discountThresholdCents > 0 && $totalGrossCents >= $discountThresholdCents) {
            $appliedRate = min(100.0, max(0.0, $discountRate));
            $discountCents = Money::percentage($totalGrossCents, $appliedRate);
        }

        $lines = [];
        $allocatedDiscount = 0;
        $lastIndex = count($grossLines) - 1;

        foreach ($grossLines as $index => $line) {
            $lineDiscount = 0;
            if ($discountCents > 0) {
                if ($index === $lastIndex) {
                    $lineDiscount = $discountCents - $allocatedDiscount;
                } elseif ($totalGrossCents > 0) {
                    $lineDiscount = (int) round(
                        $discountCents * ($line['prix_menu_brut_cents'] / $totalGrossCents),
                        0,
                        PHP_ROUND_HALF_UP
                    );
                    $allocatedDiscount += $lineDiscount;
                }
            }

            $lines[] = $line + [
                'remise_appliquee_cents' => $lineDiscount,
                'prix_menu_net_cents' => $line['prix_menu_brut_cents'] - $lineDiscount,
            ];
        }

        $menusNetCents = $totalGrossCents - $discountCents;
        $deliveryCents = max(0, $deliveryCents);

        return [
            'lignes' => $lines,
            'total_brut_cents' => $totalGrossCents,
            'remise_globale_cents' => $discountCents,
            'total_menus_net_cents' => $menusNetCents,
            'prix_livraison_cents' => $deliveryCents,
            'total_ttc_cents' => $menusNetCents + $deliveryCents,
            'taux_reduction_applique' => $appliedRate,
        ];
    }
}
