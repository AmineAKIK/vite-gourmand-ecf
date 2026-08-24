<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\CatalogIntegrityPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CatalogIntegrityPolicyTest extends TestCase
{
    public function testIdsArePositiveUniqueIntegers(): void
    {
        self::assertSame([3, 7], CatalogIntegrityPolicy::ids(['3', 7, '3']));
    }

    public function testInvalidReferenceIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CatalogIntegrityPolicy::ids([1, 'abc']);
    }

    public function testRecipeLinesAreNormalized(): void
    {
        self::assertSame([
            ['ingredient_id' => 4, 'grammage' => '0.250'],
            ['ingredient_id' => 8, 'grammage' => '2.000'],
        ], CatalogIntegrityPolicy::recipeLines([
            ['ingredient_id' => '4', 'grammage' => '0,25'],
            ['ingredient_id' => 8, 'grammage' => '2'],
        ]));
    }

    public function testDuplicateRecipeIngredientIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CatalogIntegrityPolicy::recipeLines([
            ['ingredient_id' => 4, 'grammage' => '1'],
            ['ingredient_id' => 4, 'grammage' => '2'],
        ]);
    }

    public function testZeroRecipeQuantityIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CatalogIntegrityPolicy::recipeLines([
            ['ingredient_id' => 4, 'grammage' => '0'],
        ]);
    }

    public function testInvalidMenuPriceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CatalogIntegrityPolicy::assertMenuPayload([
            'titre' => 'Menu',
            'nombre_personne_minimum' => 2,
            'prix_par_personne' => -1,
            'quantite_restante' => null,
        ]);
    }

    public function testIngredientPayloadNormalizesDecimals(): void
    {
        self::assertSame([
            'libelle' => 'Farine',
            'unite' => 'kg',
            'prix_unitaire' => '2.5000',
            'seuil_alerte' => '1.250',
        ], CatalogIntegrityPolicy::ingredientPayload([
            'libelle' => ' Farine ',
            'unite' => 'kg',
            'prix_unitaire' => '2,5',
            'seuil_alerte' => '1,25',
        ]));
    }

    public function testNegativeIngredientThresholdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CatalogIntegrityPolicy::ingredientPayload([
            'libelle' => 'Farine',
            'unite' => 'kg',
            'prix_unitaire' => '2.5',
            'seuil_alerte' => '-1',
        ]);
    }
}
