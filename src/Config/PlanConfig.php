<?php

namespace App\Config;

/**
 * Définition des plans SaaS Tugères et de leurs limites.
 */
class PlanConfig
{
    private const PLANS = [
        'starter' => [
            'label' => 'Starter',
            'prix_mois' => 29,
            'max_employes' => 1,
            'max_commandes_mois' => 50,
            'features' => [
                'signature_electronique' => false,
                'devis_premium' => false,
                'export_comptabilite' => false,
                'recettes_stocks' => false,
                'statistiques' => false,
            ],
        ],
        'pro' => [
            'label' => 'Pro',
            'prix_mois' => 59,
            'max_employes' => 3,
            'max_commandes_mois' => 200,
            'features' => [
                'signature_electronique' => true,
                'devis_premium' => true,
                'export_comptabilite' => false,
                'recettes_stocks' => true,
                'statistiques' => true,
            ],
        ],
        'premium' => [
            'label' => 'Premium',
            'prix_mois' => 99,
            'max_employes' => 0,
            'max_commandes_mois' => 0,
            'features' => [
                'signature_electronique' => true,
                'devis_premium' => true,
                'export_comptabilite' => true,
                'recettes_stocks' => true,
                'statistiques' => true,
            ],
        ],
    ];

    private static ?string $currentPlan = null;

    public static function current(): string
    {
        if (self::$currentPlan !== null) {
            return self::$currentPlan;
        }

        if (License::isSignedMode()) {
            $plan = (string) (License::entitlements()['plan'] ?? '');
        } else {
            $plan = SiteConfig::get('plan', '');
        }

        if (!array_key_exists($plan, self::PLANS)) {
            throw new \RuntimeException('Plan SaaS invalide ou indisponible.');
        }

        return self::$currentPlan = $plan;
    }

    public static function label(): string
    {
        return self::PLANS[self::current()]['label'];
    }

    public static function prixMois(): int
    {
        return self::PLANS[self::current()]['prix_mois'];
    }

    public static function maxEmployes(): int
    {
        return self::PLANS[self::current()]['max_employes'];
    }

    public static function maxCommandesMois(): int
    {
        return self::PLANS[self::current()]['max_commandes_mois'];
    }

    public static function hasFeature(string $feature): bool
    {
        $features = self::PLANS[self::current()]['features'];
        if (!array_key_exists($feature, $features)) {
            error_log('[entitlements] feature inconnue refusée: ' . $feature);
            return false;
        }
        return $features[$feature] === true;
    }

    public static function isSuspended(): bool
    {
        return SiteConfig::get('plan_suspendu', '0') === '1';
    }

    public static function all(): array
    {
        return self::PLANS;
    }

    public static function definition(string $plan): ?array
    {
        return self::PLANS[$plan] ?? null;
    }

    public static function checkCommandesQuota(): void
    {
        $max = self::maxCommandesMois();
        if ($max === 0) {
            return;
        }

        try {
            $count = db()->fetchOne(
                "SELECT COUNT(*) AS n FROM commande
                 WHERE date_commande >= DATE_FORMAT(NOW(), '%Y-%m-01')
                   AND statut != 'annulee'",
                [],
            );
        } catch (\Throwable $e) {
            error_log('[entitlements] quota commandes indisponible: ' . $e->getMessage());
            throw new \RuntimeException('Vérification du quota commandes indisponible. Réessayez plus tard.');
        }

        if ((int) ($count['n'] ?? 0) >= $max) {
            throw new \RuntimeException(
                'Quota mensuel atteint (' . $max . ' commandes). Passez au plan supérieur pour continuer à accepter des commandes.',
            );
        }
    }

    public static function checkEmployesQuota(): void
    {
        $max = self::maxEmployes();
        if ($max === 0) {
            return;
        }

        try {
            $count = db()->fetchOne(
                'SELECT COUNT(*) AS n FROM utilisateur WHERE role_id = ? AND actif = 1',
                [ROLE_ID_EMPLOYE],
            );
        } catch (\Throwable $e) {
            error_log('[entitlements] quota employés indisponible: ' . $e->getMessage());
            throw new \RuntimeException('Vérification du quota employés indisponible. Réessayez plus tard.');
        }

        if ((int) ($count['n'] ?? 0) >= $max) {
            throw new \RuntimeException(
                'Quota employés atteint (' . $max . ' employé(s) actifs). Passez au plan supérieur pour ajouter des collaborateurs.',
            );
        }
    }
}
