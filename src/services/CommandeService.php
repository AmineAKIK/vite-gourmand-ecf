<?php
// src/services/CommandeService.php

class CommandeService
{
    /**
     * Validates and extracts livraison fields from POST data.
     * Returns: date_prestation, heure_livraison, adresse_livraison, ville_livraison,
     *          code_postal_livraison, prix_livraison, prix_total (lignes total + livraison).
     * $totalMenus: sum of prix_menu for all panier items (pre-calculated by caller).
     */
    public static function payloadFromRequest(array $source, float $totalMenus): array
    {
        $payload = [
            'date_prestation'       => sanitize($source['date_prestation'] ?? ''),
            'heure_livraison'       => sanitize($source['heure_livraison'] ?? ''),
            'adresse_livraison'     => sanitize($source['adresse_livraison'] ?? ''),
            'ville_livraison'       => sanitize($source['ville_livraison'] ?? ''),
            'code_postal_livraison' => sanitize($source['code_postal_livraison'] ?? ''),
        ];

        self::validatePayload($payload);

        $prixLivraison = calculPrixLivraison($payload['ville_livraison']);

        return $payload + [
            'prix_livraison' => $prixLivraison,
            'prix_total'     => round($totalMenus + $prixLivraison, 2),
        ];
    }

    private static function validatePayload(array $payload): void
    {
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
        $dateErrors = DateTimeImmutable::getLastErrors();
        $hasDateError = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $maxDate  = new DateTimeImmutable('+365 days');
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

        if (strtolower(trim($payload['ville_livraison'])) !== 'bordeaux' && distanceKmDepuisBordeaux($payload['ville_livraison']) <= 0) {
            throw new InvalidArgumentException('Distance de livraison impossible à calculer pour cette ville.');
        }
    }
}
