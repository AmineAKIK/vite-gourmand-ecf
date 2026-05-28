<?php

namespace App\Config;

/**
 * Définition des plans SaaS Tugères et de leurs limites.
 *
 * Source de vérité : site_config.plan (starter | pro | premium).
 * Les instances existantes migrent automatiquement vers "premium" (migration 035).
 *
 * Usage :
 *   PlanConfig::current()            → 'starter' | 'pro' | 'premium'
 *   PlanConfig::maxEmployes()        → int (0 = illimité)
 *   PlanConfig::maxCommandesMois()   → int (0 = illimité)
 *   PlanConfig::hasFeature('signature_electronique') → bool
 *   PlanConfig::isSuspended()        → bool
 */
class PlanConfig
{
    private const PLANS = [
        'starter' => [
            'label'               => 'Starter',
            'prix_mois'           => 29,
            'max_employes'        => 1,
            'max_commandes_mois'  => 50,
            'features' => [
                'signature_electronique' => false,
                'devis_premium'          => false,
                'export_comptabilite'    => false,
                'recettes_stocks'        => false,
                'statistiques'          => false,
            ],
        ],
        'pro' => [
            'label'               => 'Pro',
            'prix_mois'           => 59,
            'max_employes'        => 3,
            'max_commandes_mois'  => 200,
            'features' => [
                'signature_electronique' => true,
                'devis_premium'          => true,
                'export_comptabilite'    => false,
                'recettes_stocks'        => true,
                'statistiques'          => true,
            ],
        ],
        'premium' => [
            'label'               => 'Premium',
            'prix_mois'           => 99,
            'max_employes'        => 0,
            'max_commandes_mois'  => 0,
            'features' => [
                'signature_electronique' => true,
                'devis_premium'          => true,
                'export_comptabilite'    => true,
                'recettes_stocks'        => true,
                'statistiques'          => true,
            ],
        ],
    ];

    private static ?string $currentPlan = null;

    public static function current(): string
    {
        if (self::$currentPlan === null) {
            $plan = SiteConfig::get('plan', 'premium');
            self::$currentPlan = array_key_exists($plan, self::PLANS) ? $plan : 'premium';
        }
        return self::$currentPlan;
    }

    public static function label(): string
    {
        return self::PLANS[self::current()]['label'];
    }

    public static function prixMois(): int
    {
        return self::PLANS[self::current()]['prix_mois'];
    }

    /** Nombre max d'employés. 0 = illimité. */
    public static function maxEmployes(): int
    {
        return self::PLANS[self::current()]['max_employes'];
    }

    /** Nombre max de commandes par mois calendaire. 0 = illimité. */
    public static function maxCommandesMois(): int
    {
        return self::PLANS[self::current()]['max_commandes_mois'];
    }

    public static function hasFeature(string $feature): bool
    {
        return self::PLANS[self::current()]['features'][$feature] ?? true;
    }

    public static function isSuspended(): bool
    {
        return SiteConfig::get('plan_suspendu', '0') === '1';
    }

    /** Retourne toutes les définitions de plans (pour affichage commercial). */
    public static function all(): array
    {
        return self::PLANS;
    }

    public static function definition(string $plan): ?array
    {
        return self::PLANS[$plan] ?? null;
    }

    /**
     * Vérifie si le quota commandes mensuel est atteint.
     * Fail-open si la DB est indisponible.
     */
    public static function checkCommandesQuota(): void
    {
        $max = self::maxCommandesMois();
        if ($max === 0) return;

        try {
            $count = db()->fetchOne(
                "SELECT COUNT(*) AS n FROM commande
                 WHERE date_commande >= DATE_FORMAT(NOW(), '%Y-%m-01')
                   AND statut != 'annulee'",
                []
            );
            if ((int)($count['n'] ?? 0) >= $max) {
                throw new \RuntimeException(
                    'Quota mensuel atteint (' . $max . ' commandes). '
                    . 'Passez au plan supérieur pour continuer à accepter des commandes.'
                );
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            // Fail-open : ne pas bloquer si la DB est temporairement indisponible
        }
    }

    /**
     * Vérifie si le quota employés est atteint.
     * Fail-open si la DB est indisponible.
     */
    public static function checkEmployesQuota(): void
    {
        $max = self::maxEmployes();
        if ($max === 0) return;

        try {
            $count = db()->fetchOne(
                "SELECT COUNT(*) AS n FROM utilisateur WHERE role_id = ? AND actif = 1",
                [ROLE_ID_EMPLOYE]
            );
            if ((int)($count['n'] ?? 0) >= $max) {
                throw new \RuntimeException(
                    'Quota employés atteint (' . $max . ' employé(s) actifs). '
                    . 'Passez au plan supérieur pour ajouter des collaborateurs.'
                );
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            // Fail-open
        }
    }
}
