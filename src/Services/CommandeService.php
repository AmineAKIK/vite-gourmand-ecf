<?php

namespace App\Services;

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
            'date_prestation'       => htmlspecialchars(trim($source['date_prestation']       ?? ''), ENT_QUOTES, 'UTF-8'),
            'heure_livraison'       => htmlspecialchars(trim($source['heure_livraison']       ?? ''), ENT_QUOTES, 'UTF-8'),
            'adresse_livraison'     => htmlspecialchars(trim($source['adresse_livraison']     ?? ''), ENT_QUOTES, 'UTF-8'),
            'ville_livraison'       => htmlspecialchars(trim($source['ville_livraison']       ?? ''), ENT_QUOTES, 'UTF-8'),
            'code_postal_livraison' => htmlspecialchars(trim($source['code_postal_livraison'] ?? ''), ENT_QUOTES, 'UTF-8'),
        ];

        if (
            !$payload['date_prestation']
            || !$payload['heure_livraison']
            || !$payload['adresse_livraison']
            || !$payload['ville_livraison']
            || !$payload['code_postal_livraison']
        ) {
            throw new InvalidArgumentException('Tous les champs de livraison sont obligatoires.');
        }

        $datePrestation = DateTimeImmutable::createFromFormat('!Y-m-d', $payload['date_prestation']);
        $dateErrors     = DateTimeImmutable::getLastErrors();
        $hasDateError   = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
        $tomorrow       = new DateTimeImmutable('tomorrow');
        $maxDate        = new DateTimeImmutable('+365 days');

        if (!$datePrestation || $hasDateError || $datePrestation < $tomorrow) {
            throw new InvalidArgumentException('La date de prestation doit être au minimum demain.');
        }
        if ($datePrestation > $maxDate) {
            throw new InvalidArgumentException('La date de prestation ne peut pas dépasser 1 an à l\'avance.');
        }

        $heureObj = \DateTime::createFromFormat('H:i', $payload['heure_livraison']);
        if (!$heureObj || $heureObj->format('H:i') !== $payload['heure_livraison']) {
            throw new InvalidArgumentException('Format d\'heure invalide (HH:MM).');
        }
        $minutes = ((int)$heureObj->format('H') * 60) + (int)$heureObj->format('i');
        if ($minutes < 7 * 60 || $minutes > 22 * 60) {
            throw new InvalidArgumentException('L\'heure de livraison doit être entre 07:00 et 22:00.');
        }

        if (!preg_match('/^\d{5}$/', $payload['code_postal_livraison'])) {
            throw new InvalidArgumentException('Code postal invalide (5 chiffres requis).');
        }
        if (strlen($payload['adresse_livraison']) < 3) {
            throw new InvalidArgumentException('Adresse de livraison invalide.');
        }
        if (strlen($payload['ville_livraison']) < 2) {
            throw new InvalidArgumentException('Ville de livraison invalide.');
        }
    }
}
