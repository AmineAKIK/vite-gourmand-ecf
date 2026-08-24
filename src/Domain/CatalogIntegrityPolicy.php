<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final class CatalogIntegrityPolicy
{
    /**
     * @return list<int>
     */
    public static function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new InvalidArgumentException('Identifiant de référentiel invalide.');
            }
            $ids[(int) $id] = (int) $id;
        }

        return array_values($ids);
    }

    /**
     * @return list<array{ingredient_id:int,grammage:string}>
     */
    public static function recipeLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $ingredientId = filter_var($line['ingredient_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($ingredientId === false) {
                throw new InvalidArgumentException('Ingrédient de recette invalide.');
            }

            $raw = str_replace(',', '.', trim((string) ($line['grammage'] ?? '')));
            if ($raw === '' || !is_numeric($raw) || (float) $raw <= 0) {
                throw new InvalidArgumentException('Chaque quantité de recette doit être strictement positive.');
            }
            if (isset($normalized[(int) $ingredientId])) {
                throw new InvalidArgumentException('Un ingrédient ne peut apparaître qu’une fois dans une recette.');
            }

            $normalized[(int) $ingredientId] = [
                'ingredient_id' => (int) $ingredientId,
                'grammage' => number_format((float) $raw, 3, '.', ''),
            ];
        }

        return array_values($normalized);
    }

    public static function assertMenuPayload(array $data): void
    {
        if (trim((string) ($data['titre'] ?? '')) === '') {
            throw new InvalidArgumentException('Le titre du menu est obligatoire.');
        }
        if ((int) ($data['nombre_personne_minimum'] ?? 0) < 1) {
            throw new InvalidArgumentException('Le minimum de personnes doit être supérieur à zéro.');
        }
        if (!is_numeric($data['prix_par_personne'] ?? null) || (float) $data['prix_par_personne'] < 0) {
            throw new InvalidArgumentException('Le prix par personne est invalide.');
        }
        if (($data['quantite_restante'] ?? null) !== null && (int) $data['quantite_restante'] < 0) {
            throw new InvalidArgumentException('La quantité restante ne peut pas être négative.');
        }
    }

    public static function assertPlatPayload(array $data): void
    {
        if (trim((string) ($data['titre'] ?? '')) === '') {
            throw new InvalidArgumentException('Le titre du plat est obligatoire.');
        }
        if ((int) ($data['categorie_id'] ?? 0) < 1) {
            throw new InvalidArgumentException('La catégorie du plat est obligatoire.');
        }
    }

    /**
     * @return array{libelle:string,unite:string,prix_unitaire:string,seuil_alerte:?string}
     */
    public static function ingredientPayload(array $data): array
    {
        $libelle = trim((string) ($data['libelle'] ?? ''));
        $unite = trim((string) ($data['unite'] ?? 'kg'));
        $priceRaw = str_replace(',', '.', trim((string) ($data['prix_unitaire'] ?? '0')));
        $thresholdRaw = str_replace(',', '.', trim((string) ($data['seuil_alerte'] ?? '')));

        if ($libelle === '') {
            throw new InvalidArgumentException('Le libellé de l’ingrédient est obligatoire.');
        }
        if ($unite === '') {
            throw new InvalidArgumentException('L’unité de l’ingrédient est obligatoire.');
        }
        if (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
            throw new InvalidArgumentException('Le prix unitaire de l’ingrédient est invalide.');
        }
        if ($thresholdRaw !== '' && (!is_numeric($thresholdRaw) || (float) $thresholdRaw < 0)) {
            throw new InvalidArgumentException('Le seuil d’alerte de l’ingrédient est invalide.');
        }

        return [
            'libelle' => $libelle,
            'unite' => $unite,
            'prix_unitaire' => number_format((float) $priceRaw, 4, '.', ''),
            'seuil_alerte' => $thresholdRaw === '' ? null : number_format((float) $thresholdRaw, 3, '.', ''),
        ];
    }
}
