<?php

namespace App\Config;

use OutOfBoundsException;
use RuntimeException;

final class ConfigurationRegistry
{
    /** @var array<string,ConfigurationDefinition>|null */
    private static ?array $definitions = null;

    /** @return array<string,ConfigurationDefinition> */
    public static function all(): array
    {
        if (self::$definitions === null) {
            self::$definitions = self::build();
            self::assertRegistryIntegrity(self::$definitions);
        }

        return self::$definitions;
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function get(string $key): ConfigurationDefinition
    {
        $definition = self::all()[$key] ?? null;
        if (!$definition instanceof ConfigurationDefinition) {
            throw new OutOfBoundsException('Unknown configuration key: ' . $key);
        }

        return $definition;
    }

    public static function byStorageKey(ConfigurationSource $source, string $storageKey): ConfigurationDefinition
    {
        foreach (self::all() as $definition) {
            if ($definition->source === $source && $definition->storageKey === $storageKey) {
                return $definition;
            }
        }

        throw new OutOfBoundsException('Unknown configuration storage key: ' . $storageKey);
    }

    /** @return array<string,ConfigurationDefinition> */
    public static function forScope(ConfigurationScope $scope): array
    {
        return array_filter(
            self::all(),
            static fn(ConfigurationDefinition $definition): bool => $definition->scope === $scope,
        );
    }

    /** @return array<string,ConfigurationDefinition> */
    public static function forGroup(string $group): array
    {
        return array_filter(
            self::all(),
            static fn(ConfigurationDefinition $definition): bool => $definition->group === $group,
        );
    }

    /** @return array<string,ConfigurationDefinition> */
    public static function siteConfigDefinitions(): array
    {
        return array_filter(
            self::all(),
            static fn(ConfigurationDefinition $definition): bool => $definition->source === ConfigurationSource::SITE_CONFIG,
        );
    }

    /** @return array<string,ConfigurationDefinition> */
    private static function build(): array
    {
        $admin = 'administrateur';

        $definitions = [
            self::fixed('market.country', ConfigurationType::STRING, 'FR', 'market', 'Pays du profil marché V1.'),
            self::fixed('market.currency', ConfigurationType::STRING, 'EUR', 'market', 'Devise canonique du profil marché V1.'),
            self::fixed('market.locale', ConfigurationType::STRING, 'fr-FR', 'market', 'Locale canonique du profil marché V1.'),
            self::fixed('market.timezone', ConfigurationType::STRING, 'Europe/Paris', 'market', 'Fuseau horaire canonique du profil marché V1.'),

            self::tenant('brand.name', 'site_nom', ConfigurationType::STRING, true, $admin, 'identity', 'Nom commercial public.', null, ['max_length' => 100]),
            self::tenant('brand.slogan', 'site_slogan', ConfigurationType::STRING, false, $admin, 'identity', 'Slogan public.', null, ['max_length' => 100]),
            self::tenant('brand.domain', 'site_domaine', ConfigurationType::STRING, false, $admin, 'identity', 'Domaine canonique public.', null, ['max_length' => 190]),
            self::tenant('contact.email', 'site_email', ConfigurationType::EMAIL, true, $admin, 'identity', 'Email public du traiteur.', null, ['max_length' => 190]),
            self::tenant('contact.phone', 'site_telephone', ConfigurationType::STRING, true, $admin, 'identity', 'Téléphone public du traiteur.', null, ['max_length' => 30]),
            self::tenant('contact.address.line1', 'site_adresse', ConfigurationType::STRING, false, $admin, 'identity', 'Adresse publique.', null, ['max_length' => 150]),
            self::tenant('contact.address.postal_code', 'site_code_postal', ConfigurationType::POSTAL_CODE, false, $admin, 'identity', 'Code postal public.'),
            self::tenant('contact.address.city', 'site_ville', ConfigurationType::STRING, false, $admin, 'identity', 'Ville publique.', null, ['max_length' => 80]),

            self::tenant('theme.primary_color', 'couleur_principale', ConfigurationType::COLOR, false, $admin, 'appearance', 'Couleur principale de marque.', '#1F2937'),
            self::tenant('theme.secondary_color', 'couleur_secondaire', ConfigurationType::COLOR, false, $admin, 'appearance', 'Couleur secondaire de marque.', '#6B7280'),
            self::tenant('theme.background_color', 'couleur_fond', ConfigurationType::COLOR, false, $admin, 'appearance', 'Couleur de fond.', '#FFFFFF'),

            self::tenant('delivery.origin.latitude', 'livraison_lat', ConfigurationType::COORDINATE, false, $admin, 'delivery', 'Latitude du point de départ livraison.', null, ['min' => -90.0, 'max' => 90.0]),
            self::tenant('delivery.origin.longitude', 'livraison_lng', ConfigurationType::COORDINATE, false, $admin, 'delivery', 'Longitude du point de départ livraison.', null, ['min' => -180.0, 'max' => 180.0]),
            self::tenant('delivery.radius_km', 'livraison_rayon_max_km', ConfigurationType::INTEGER, false, $admin, 'delivery', 'Rayon maximal de livraison en kilomètres.', null, ['min' => 1, 'max' => 500]),
            self::tenant('delivery.free_postal_codes', 'livraison_codes_postaux_gratuits', ConfigurationType::STRING_LIST, false, $admin, 'delivery', 'Codes postaux exemptés de frais de livraison.', null, ['max_items' => 100]),
            self::tenant('delivery.base_fee', 'livraison_base', ConfigurationType::DECIMAL, false, $admin, 'delivery', 'Frais fixes de livraison.', null, ['min' => 0.0]),
            self::tenant('delivery.per_km_fee', 'livraison_km', ConfigurationType::DECIMAL, false, $admin, 'delivery', 'Frais variables par kilomètre.', null, ['min' => 0.0]),
            self::tenant('order.capacity.max_per_day', 'commandes_max_par_jour', ConfigurationType::INTEGER, false, $admin, 'orders', 'Capacité maximale de commandes par jour.', null, ['min' => 0, 'max' => 999]),

            self::tenant('business.legal_name', 'entreprise_nom', ConfigurationType::STRING, true, $admin, 'business', 'Raison sociale utilisée sur les documents.', null, ['max_length' => 100]),
            self::tenant('business.siret', 'entreprise_siret', ConfigurationType::SIRET, true, $admin, 'business', 'SIRET de l’entreprise.'),
            self::tenant('business.legal_form', 'entreprise_forme_juridique', ConfigurationType::STRING, false, $admin, 'business', 'Forme juridique.', null, ['max_length' => 60]),
            self::tenant('business.address.line1', 'entreprise_adresse', ConfigurationType::STRING, true, $admin, 'business', 'Adresse légale.', null, ['max_length' => 150]),
            self::tenant('business.address.postal_code', 'entreprise_code_postal', ConfigurationType::POSTAL_CODE, true, $admin, 'business', 'Code postal légal.'),
            self::tenant('business.address.city', 'entreprise_ville', ConfigurationType::STRING, true, $admin, 'business', 'Ville légale.', null, ['max_length' => 80]),
            self::tenant('business.phone', 'entreprise_telephone', ConfigurationType::STRING, false, $admin, 'business', 'Téléphone légal ou administratif.', null, ['max_length' => 30]),
            self::tenant('business.email', 'entreprise_email', ConfigurationType::EMAIL, true, $admin, 'business', 'Email légal ou administratif.', null, ['max_length' => 190]),
            self::tenant('business.vat_number', 'entreprise_tva_intracom', ConfigurationType::STRING, false, $admin, 'business', 'Numéro de TVA intracommunautaire.', null, ['max_length' => 20]),

            self::tenant('banking.iban', 'banque_iban', ConfigurationType::IBAN, false, $admin, 'banking', 'IBAN affiché lorsque nécessaire.'),
            self::tenant('banking.bic', 'banque_bic', ConfigurationType::BIC, false, $admin, 'banking', 'BIC associé au compte bancaire.'),
            self::tenant('banking.bank_name', 'banque_nom_banque', ConfigurationType::STRING, false, $admin, 'banking', 'Nom de la banque.', null, ['max_length' => 80]),

            self::tenant('tax.regime', 'regime_tva', ConfigurationType::ENUM, true, $admin, 'tax', 'Régime TVA appliqué aux documents.', null, ['values' => ['assujetti', 'non_assujetti']]),
            self::tenant('billing.invoice.notice', 'mention_facture', ConfigurationType::TEXT, false, $admin, 'billing', 'Mention complémentaire de facture.', null, ['max_length' => 500]),
            self::tenant('billing.receipt.notice', 'mention_ticket', ConfigurationType::TEXT, false, $admin, 'billing', 'Mention complémentaire de ticket.', null, ['max_length' => 500]),
            self::tenant('billing.deposit.notice', 'mention_acompte', ConfigurationType::TEXT, false, $admin, 'billing', 'Mention complémentaire d’acompte.', null, ['max_length' => 500]),

            self::tenant('payment.deposit.default_rate_percent', 'acompte_taux_defaut', ConfigurationType::INTEGER, false, $admin, 'payment', 'Pourcentage d’acompte par défaut.', null, ['min' => 0, 'max' => 100]),
            self::tenant('payment.terms_days', 'delai_paiement_jours', ConfigurationType::INTEGER, false, $admin, 'payment', 'Délai de paiement en jours.', null, ['min' => 0, 'max' => 365]),
            self::tenant('payment.late_fee_rate_percent', 'penalites_retard_taux', ConfigurationType::DECIMAL, false, $admin, 'payment', 'Taux de pénalités de retard.', null, ['min' => 0.0]),
            self::tenant('payment.recovery_fee', 'indemnite_recouvrement', ConfigurationType::DECIMAL, false, $admin, 'payment', 'Indemnité forfaitaire de recouvrement.', null, ['min' => 0.0]),

            self::tenant('discount.threshold', 'reduction_seuil', ConfigurationType::DECIMAL, false, $admin, 'pricing', 'Seuil déclenchant une remise commerciale.', null, ['min' => 0.0]),
            self::tenant('discount.rate_percent', 'reduction_taux', ConfigurationType::INTEGER, false, $admin, 'pricing', 'Pourcentage de remise commerciale.', null, ['min' => 0, 'max' => 100]),

            self::tenant('content.home.hero_subtitle', 'hero_sous_titre', ConfigurationType::STRING, false, $admin, 'content', 'Sous-titre du hero de la page d’accueil.', null, ['max_length' => 60]),
            self::tenant('content.home.hero_paragraph', 'hero_paragraphe', ConfigurationType::TEXT, false, $admin, 'content', 'Paragraphe du hero de la page d’accueil.', null, ['max_length' => 200]),
            self::tenant('legal.terms_content', 'cgv_contenu', ConfigurationType::TEXT, false, $admin, 'legal', 'Contenu des conditions générales.', null, ['max_length' => 20000]),
            self::tenant('legal.notices_content', 'mentions_contenu', ConfigurationType::TEXT, false, $admin, 'legal', 'Contenu des mentions légales.', null, ['max_length' => 20000]),
            self::tenant('quote.template', 'devis_template', ConfigurationType::ENUM, false, $admin, 'quote', 'Présentation des devis.', 'sobre', ['values' => ['sobre', 'premium']]),

            self::operator('operator.database.host', 'DB_HOST', ConfigurationType::STRING, true, false, 'database', 'Hôte MySQL.'),
            self::operator('operator.database.name', 'DB_NAME', ConfigurationType::STRING, true, false, 'database', 'Nom de la base MySQL.'),
            self::operator('operator.database.user', 'DB_USER', ConfigurationType::STRING, true, false, 'database', 'Utilisateur MySQL.'),
            self::operator('operator.database.password', 'DB_PASS', ConfigurationType::STRING, true, true, 'database', 'Mot de passe MySQL.'),
            self::operator('operator.stripe.secret_key', 'STRIPE_SECRET_KEY', ConfigurationType::STRING, false, true, 'payment', 'Clé secrète Stripe.'),
            self::operator('operator.stripe.publishable_key', 'STRIPE_PUBLISHABLE_KEY', ConfigurationType::STRING, false, false, 'payment', 'Clé publique Stripe.'),
            self::operator('operator.stripe.webhook_secret', 'STRIPE_WEBHOOK_SECRET', ConfigurationType::STRING, false, true, 'payment', 'Secret de signature webhook Stripe.'),
            self::operator('operator.mail.brevo_api_key', 'BREVO_API_KEY', ConfigurationType::STRING, false, true, 'mail', 'Clé API Brevo.'),
            self::operator('operator.mail.from_address', 'MAIL_FROM', ConfigurationType::EMAIL, false, false, 'mail', 'Adresse technique d’expédition email.'),
            self::operator('operator.cron.token', 'CRON_SECRET_TOKEN', ConfigurationType::STRING, false, true, 'cron', 'Secret d’authentification des tâches planifiées.'),
            self::operator('operator.base_url', 'BASE_URL', ConfigurationType::STRING, true, false, 'runtime', 'URL publique de l’instance.'),
            self::operator('operator.app_env', 'APP_ENV', ConfigurationType::ENUM, true, false, 'runtime', 'Environnement d’exécution.', 'production', ['values' => ['development', 'test', 'production']]),
        ];

        $indexed = [];
        foreach ($definitions as $definition) {
            $indexed[$definition->key] = $definition;
        }

        return $indexed;
    }

    /** @param array<string,ConfigurationDefinition> $definitions */
    private static function assertRegistryIntegrity(array $definitions): void
    {
        $seenStorageKeys = [];
        foreach ($definitions as $key => $definition) {
            if ($key !== $definition->key) {
                throw new RuntimeException('Configuration registry index mismatch: ' . $key);
            }

            if ($definition->source === ConfigurationSource::FIXED) {
                if (!$definition->hasDefault()) {
                    throw new RuntimeException('Fixed configuration requires a value: ' . $key);
                }
                continue;
            }

            $storageIdentity = $definition->source->value . ':' . $definition->storageKey;
            if (isset($seenStorageKeys[$storageIdentity])) {
                throw new RuntimeException('Duplicate configuration storage key: ' . $storageIdentity);
            }
            $seenStorageKeys[$storageIdentity] = true;

            if ($definition->source === ConfigurationSource::ENVIRONMENT
                && $definition->scope !== ConfigurationScope::OPERATOR) {
                throw new RuntimeException('Environment configuration must use operator scope: ' . $key);
            }
        }
    }

    /** @param array<string,mixed> $constraints */
    private static function tenant(
        string $key,
        string $storageKey,
        ConfigurationType $type,
        bool $required,
        string $editableRole,
        string $group,
        string $description,
        mixed $defaultValue = null,
        array $constraints = [],
    ): ConfigurationDefinition {
        return new ConfigurationDefinition(
            key: $key,
            scope: ConfigurationScope::TENANT,
            type: $type,
            source: ConfigurationSource::SITE_CONFIG,
            storageKey: $storageKey,
            required: $required,
            sensitive: false,
            editableRole: $editableRole,
            group: $group,
            description: $description,
            defaultValue: $defaultValue,
            constraints: $constraints,
        );
    }

    /** @param array<string,mixed> $constraints */
    private static function operator(
        string $key,
        string $storageKey,
        ConfigurationType $type,
        bool $required,
        bool $sensitive,
        string $group,
        string $description,
        mixed $defaultValue = null,
        array $constraints = [],
    ): ConfigurationDefinition {
        return new ConfigurationDefinition(
            key: $key,
            scope: ConfigurationScope::OPERATOR,
            type: $type,
            source: ConfigurationSource::ENVIRONMENT,
            storageKey: $storageKey,
            required: $required,
            sensitive: $sensitive,
            editableRole: 'operator',
            group: $group,
            description: $description,
            defaultValue: $defaultValue,
            constraints: $constraints,
            migrationStrategy: 'environment_only',
        );
    }

    private static function fixed(
        string $key,
        ConfigurationType $type,
        mixed $value,
        string $group,
        string $description,
    ): ConfigurationDefinition {
        return new ConfigurationDefinition(
            key: $key,
            scope: ConfigurationScope::MARKET,
            type: $type,
            source: ConfigurationSource::FIXED,
            storageKey: null,
            required: true,
            sensitive: false,
            editableRole: null,
            group: $group,
            description: $description,
            defaultValue: $value,
            migrationStrategy: 'fixed_market_profile',
        );
    }
}
