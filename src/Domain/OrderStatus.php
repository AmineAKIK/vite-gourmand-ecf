<?php

namespace App\Domain;

class OrderStatus
{
    public static function definitions(): array
    {
        return [
            'en_attente' => [
                'label'       => 'En attente',
                'class'       => 'statut-en_attente',
                'transitions' => ['accepte', 'annulee'],
            ],
            'accepte' => [
                'label'       => 'Accepté',
                'class'       => 'statut-accepte',
                'transitions' => ['en_preparation', 'annulee'],
            ],
            'en_preparation' => [
                'label'       => 'En préparation',
                'class'       => 'statut-en_preparation',
                'transitions' => ['en_cours_livraison', 'annulee'],
            ],
            'en_cours_livraison' => [
                'label'       => 'En cours de livraison',
                'class'       => 'statut-en_cours_livraison',
                'transitions' => ['livre', 'annulee'],
            ],
            'livre' => [
                'label'       => 'Livré',
                'class'       => 'statut-livre',
                'transitions' => ['en_attente_materiel', 'terminee'],
            ],
            'en_attente_materiel' => [
                'label'       => 'En attente du retour de matériel',
                'class'       => 'statut-en_attente_materiel',
                'transitions' => ['terminee', 'annulee'],
            ],
            'terminee' => [
                'label'       => 'Terminée',
                'class'       => 'statut-terminee',
                'transitions' => [],
            ],
            'annulee' => [
                'label'       => 'Annulée',
                'class'       => 'statut-annulee',
                'transitions' => [],
            ],
        ];
    }

    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    public static function initial(): string   { return 'en_attente'; }
    public static function cancelled(): string { return 'annulee'; }
    public static function accepted(): string  { return 'accepte'; }
    public static function completed(): string { return 'terminee'; }
    public static function preparing(): string { return 'en_preparation'; }
    public static function delivering(): string { return 'en_cours_livraison'; }
    public static function awaitingMaterial(): string { return 'en_attente_materiel'; }

    public static function revenueStatuses(): array
    {
        return [
            self::accepted(),
            self::preparing(),
            self::delivering(),
            'livre',
            self::awaitingMaterial(),
            self::completed(),
        ];
    }

    public static function countsTowardRevenue(?string $status): bool
    {
        return in_array($status, self::revenueStatuses(), true);
    }

    public static function isValid(string $status): bool
    {
        return array_key_exists($status, self::definitions());
    }

    public static function label(?string $status): string
    {
        $status = $status ?? '';
        return self::definitions()[$status]['label'] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function cssClass(?string $status): string
    {
        $status = $status ?? '';
        return self::definitions()[$status]['class'] ?? 'statut-en_attente';
    }

    public static function badge(?string $status): string
    {
        return '<span class="badge-statut ' . htmlspecialchars(self::cssClass($status), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(self::label($status), ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    public static function canTransition(?string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        $defs = self::definitions();
        if (!$from || !isset($defs[$from], $defs[$to])) {
            return false;
        }
        return in_array($to, $defs[$from]['transitions'], true);
    }

    public static function clientCanModify(array $commande): bool
    {
        return ($commande['statut'] ?? '') === self::initial();
    }

    public static function clientCanTrack(?string $status): bool
    {
        $trackable = array_diff(
            self::all(),
            [self::initial(), self::cancelled(), self::completed()]
        );
        return in_array($status, $trackable, true);
    }

    public static function clientCanReview(?string $status): bool
    {
        return $status === self::completed();
    }

    public static function countByStatus(array $commandes, string $status): int
    {
        return count(array_filter($commandes, fn($c) => ($c['statut'] ?? '') === $status));
    }
}
