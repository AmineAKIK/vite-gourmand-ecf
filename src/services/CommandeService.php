<?php
// src/services/CommandeService.php

class CommandeService
{
    public static function payloadFromRequest(array $source, array $menu): array
    {
        $payload = [
            'date_prestation'       => sanitize($source['date_prestation'] ?? ''),
            'heure_livraison'       => sanitize($source['heure_livraison'] ?? ''),
            'adresse_livraison'     => sanitize($source['adresse_livraison'] ?? ''),
            'ville_livraison'       => sanitize($source['ville_livraison'] ?? ''),
            'code_postal_livraison' => sanitize($source['code_postal_livraison'] ?? ''),
            'nombre_personne'       => (int)($source['nombre_personne'] ?? 0),
        ];

        self::validatePayload($payload, $menu);

        $prixMenu = calculPrixMenu(
            (float)$menu['prix_par_personne'],
            $payload['nombre_personne'],
            (int)$menu['nombre_personne_minimum']
        );
        $prixLivraison = calculPrixLivraison($payload['ville_livraison']);

        return $payload + [
            'prix_menu'      => $prixMenu,
            'prix_livraison' => $prixLivraison,
            'prix_total'     => round($prixMenu + $prixLivraison, 2),
        ];
    }

    private static function validatePayload(array $payload, array $menu): void
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

        // Validation date
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

        // Validation heure (format HH:MM, plage 07:00–22:00)
        if (!preg_match('/^\d{2}:\d{2}$/', $payload['heure_livraison'])) {
            throw new InvalidArgumentException('Format d\'heure invalide (HH:MM).');
        }
        [$h, $m] = explode(':', $payload['heure_livraison']);
        if ((int)$h < 7 || (int)$h > 22 || (int)$m > 59) {
            throw new InvalidArgumentException('L\'heure de livraison doit être entre 07:00 et 22:00.');
        }

        // Validation code postal
        if (!preg_match('/^\d{5}$/', $payload['code_postal_livraison'])) {
            throw new InvalidArgumentException('Code postal invalide (5 chiffres requis).');
        }

        // Validation adresse et ville
        if (strlen($payload['adresse_livraison']) < 3) {
            throw new InvalidArgumentException('Adresse de livraison invalide.');
        }
        if (strlen($payload['ville_livraison']) < 2) {
            throw new InvalidArgumentException('Ville de livraison invalide.');
        }

        // Validation nombre de personnes
        $minimum = (int)$menu['nombre_personne_minimum'];
        if ($payload['nombre_personne'] < $minimum) {
            throw new InvalidArgumentException('Nombre de personnes insuffisant (minimum : ' . $minimum . ').');
        }
        if ($payload['nombre_personne'] > 500) {
            throw new InvalidArgumentException('Nombre de personnes trop élevé (maximum : 500).');
        }

        if (strtolower(trim($payload['ville_livraison'])) !== 'bordeaux' && distanceKmDepuisBordeaux($payload['ville_livraison']) <= 0) {
            throw new InvalidArgumentException('Distance de livraison impossible à calculer pour cette ville.');
        }
    }
}
