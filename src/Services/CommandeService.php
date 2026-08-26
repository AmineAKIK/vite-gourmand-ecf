<?php

namespace App\Services;

use App\Config\Configuration;
use App\Domain\BusinessPolicy;
use App\Domain\InputPolicy;
use DateTimeImmutable;
use InvalidArgumentException;

class CommandeService
{
    /**
     * Valide uniquement les champs de formulaire de livraison depuis $_POST.
     * Lève InvalidArgumentException si une règle est violée.
     * Ne calcule aucun prix — c'est le rôle de PricingService.
     */
    public static function validateLivraisonFields(array $source): void
    {
        $payload = [
            'date_prestation'       => InputPolicy::date($source['date_prestation'] ?? ''),
            'heure_livraison'       => InputPolicy::time($source['heure_livraison'] ?? ''),
            'adresse_livraison'     => InputPolicy::text($source['adresse_livraison'] ?? '', 180, true),
            'ville_livraison'       => InputPolicy::text($source['ville_livraison'] ?? '', 100, true),
            'code_postal_livraison' => InputPolicy::postalCode($source['code_postal_livraison'] ?? ''),
        ];

        $datePrestation = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $payload['date_prestation'] . ' ' . $payload['heure_livraison']);
        if (!$datePrestation) {
            throw new InvalidArgumentException('Date ou heure de prestation invalide.');
        }

        $policy = new BusinessPolicy(static fn(string $key): mixed => Configuration::get($key));
        $policy->assertOrderSchedule($datePrestation, new DateTimeImmutable());

        $heureObj = DateTimeImmutable::createFromFormat('!H:i', $payload['heure_livraison']);
        if (!$heureObj) {
            throw new InvalidArgumentException('Format d\'heure invalide (HH:MM).');
        }
        $minutes = ((int) $heureObj->format('H') * 60) + (int) $heureObj->format('i');
        if ($minutes < 7 * 60 || $minutes > 22 * 60) {
            throw new InvalidArgumentException('L\'heure de livraison doit être entre 07:00 et 22:00.');
        }

        if (mb_strlen($payload['adresse_livraison']) < 3) {
            throw new InvalidArgumentException('Adresse de livraison invalide.');
        }
        if (mb_strlen($payload['ville_livraison']) < 2) {
            throw new InvalidArgumentException('Ville de livraison invalide.');
        }
    }
}
