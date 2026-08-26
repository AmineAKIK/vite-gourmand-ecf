<?php

namespace App\Domain;

final class OrderPricingCalculator
{
    /**
     * @param array<int, array{menu_id:int|string, prix_par_personne:string|int, nombre_personne:int|string}> $items
     * @return array{
     *   lignes: array<int, array<string, int>>,
     *   total_brut_cents:int,
     *   remise_globale_cents:int,
     *   total_menus_net_cents:int,
     *   prix_livraison_cents:int,
     *   total_ttc_cents:int,
     *   taux_reduction_basis_points:int
     * }
     */
    public static function calculate(
        array $items,
        int $deliveryCents,
        int $discountThresholdCents,
        int $discountRateBasisPoints,
    ): array {
        $grossLines = [];
        $totalGrossCents = 0;

        foreach ($items as $item) {
            $unitCents = Money::fromDecimal((string) $item['prix_par_personne']);
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

        $appliedBasisPoints = 0;
        $discountCents = 0;
        if ($discountThresholdCents > 0 && $totalGrossCents >= $discountThresholdCents) {
            $appliedBasisPoints = min(10000, max(0, $discountRateBasisPoints));
            $discountCents = Money::percentageBasisPoints($totalGrossCents, $appliedBasisPoints);
        }

        $lines = [];
        $allocatedDiscount = 0;
        $lastIndex = count($grossLines) - 1;

        foreach ($grossLines as $index => $line) {
            $lineDiscount = 0;
            if ($discountCents > 0) {
                if ($index === $lastIndex) {
                    $lineDiscount = $discountCents - $allocatedDiscount;
                } else {
                    $lineDiscount = Money::allocateProportionally(
                        $discountCents,
                        $line['prix_menu_brut_cents'],
                        $totalGrossCents,
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
            'taux_reduction_basis_points' => $appliedBasisPoints,
        ];
    }
}
