<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\ConfigurationIncompleteException;
use App\Config\ConfigurationInvalidException;
use App\Config\ConfigurationMissingException;
use App\Config\Database;
use App\Config\OperatorConfiguration;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class PaymentMethodRegistry
{
    public const CHECKOUT_STRATEGY_CREATE_ORDER = 'create_order';
    public const CHECKOUT_STRATEGY_PROVIDER_CONFIRMATION = 'provider_confirmation';

    public const ORDER_EFFECT_CREATE_INITIAL = 'create_initial_order';
    public const ORDER_EFFECT_CONFIRM_AFTER_PROVIDER = 'confirm_after_provider';

    /**
     * Product capabilities are code invariants. Tenant policy lives in mode_paiement.
     *
     * @var array<string,array{
     *   label:string,
     *   provider:?string,
     *   checkout_strategy:string,
     *   order_status_effect:string,
     *   supports_manual_collection:bool
     * }>
     */
    private const CAPABILITIES = [
        'virement' => [
            'label' => 'Virement bancaire',
            'provider' => null,
            'checkout_strategy' => self::CHECKOUT_STRATEGY_CREATE_ORDER,
            'order_status_effect' => self::ORDER_EFFECT_CREATE_INITIAL,
            'supports_manual_collection' => true,
        ],
        'cheque' => [
            'label' => 'Chèque',
            'provider' => null,
            'checkout_strategy' => self::CHECKOUT_STRATEGY_CREATE_ORDER,
            'order_status_effect' => self::ORDER_EFFECT_CREATE_INITIAL,
            'supports_manual_collection' => true,
        ],
        'especes' => [
            'label' => 'Espèces',
            'provider' => null,
            'checkout_strategy' => self::CHECKOUT_STRATEGY_CREATE_ORDER,
            'order_status_effect' => self::ORDER_EFFECT_CREATE_INITIAL,
            'supports_manual_collection' => true,
        ],
        'cb_online' => [
            'label' => 'Carte bancaire en ligne',
            'provider' => 'stripe',
            'checkout_strategy' => self::CHECKOUT_STRATEGY_PROVIDER_CONFIRMATION,
            'order_status_effect' => self::ORDER_EFFECT_CONFIRM_AFTER_PROVIDER,
            'supports_manual_collection' => false,
        ],
    ];

    /** @return array<string,array<string,mixed>> */
    public static function capabilities(): array
    {
        return self::CAPABILITIES;
    }

    /** @return list<array<string,mixed>> */
    public static function tenantPolicies(): array
    {
        $rows = self::loadRows();
        $policies = [];

        foreach (self::CAPABILITIES as $code => $capability) {
            $policies[] = self::policy($code, $capability, $rows[$code] ?? null);
        }

        return $policies;
    }

    /** @return list<array<string,mixed>> */
    public static function checkoutMethods(): array
    {
        return array_values(array_filter(
            self::tenantPolicies(),
            static fn(array $method): bool => $method['actif']
                && $method['checkout_enabled']
                && $method['provider_ready'],
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function manualCollectionMethods(?string $paymentType = null): array
    {
        if ($paymentType !== null) {
            self::assertPaymentType($paymentType);
        }

        return array_values(array_filter(
            self::tenantPolicies(),
            static function (array $method) use ($paymentType): bool {
                if (!$method['actif']
                    || !$method['manual_collection_enabled']
                    || !$method['supports_manual_collection']) {
                    return false;
                }

                return $paymentType === null || self::allowsPaymentType($method, $paymentType);
            },
        ));
    }

    /** @return array<string,mixed> */
    public static function requireCheckoutMethod(string $code): array
    {
        $method = self::findPolicy(trim($code));
        if (!$method['actif'] || !$method['checkout_enabled']) {
            throw new InvalidArgumentException('Mode de paiement indisponible pour le checkout.');
        }

        if (!$method['provider_ready']) {
            $missing = $method['provider_missing_keys'];
            throw new ConfigurationIncompleteException(
                $missing !== [] ? $missing : ['payment.provider'],
                'payment:' . $method['code'],
            );
        }

        return $method;
    }

    /** @return array<string,mixed> */
    public static function requireManualCollectionMethod(string $code, string $paymentType): array
    {
        self::assertPaymentType($paymentType);
        $method = self::findPolicy(trim($code));

        if (!$method['actif']
            || !$method['manual_collection_enabled']
            || !$method['supports_manual_collection']) {
            throw new InvalidArgumentException('Mode de paiement indisponible pour un encaissement manuel.');
        }
        if (!self::allowsPaymentType($method, $paymentType)) {
            throw new InvalidArgumentException('Ce mode de paiement ne permet pas ce type d’encaissement.');
        }

        return $method;
    }

    public static function assertCheckoutAvailable(): void
    {
        $configured = array_values(array_filter(
            self::tenantPolicies(),
            static fn(array $method): bool => $method['actif'] && $method['checkout_enabled'],
        ));

        if ($configured === []) {
            throw new ConfigurationIncompleteException(['payment.methods'], 'checkout');
        }

        foreach ($configured as $method) {
            if ($method['provider_ready']) {
                return;
            }
        }

        $missing = [];
        foreach ($configured as $method) {
            foreach ($method['provider_missing_keys'] as $key) {
                $missing[$key] = true;
            }
        }

        $keys = array_keys($missing);
        sort($keys);
        throw new ConfigurationIncompleteException(
            $keys !== [] ? $keys : ['payment.methods'],
            'checkout',
        );
    }

    public static function saveTenantPolicy(
        string $code,
        bool $active,
        bool $checkoutEnabled,
        bool $manualCollectionEnabled,
        bool $allowDeposit,
        bool $allowBalance,
        bool $allowSinglePayment,
        string $instructions,
    ): void {
        $code = trim($code);
        $capability = self::capability($code);
        $instructions = trim($instructions);

        if (mb_strlen($instructions) > 2000) {
            throw new InvalidArgumentException('Les instructions de paiement sont trop longues.');
        }
        if (!$active && ($checkoutEnabled || $manualCollectionEnabled)) {
            throw new InvalidArgumentException('Une méthode désactivée ne peut être disponible dans un parcours.');
        }
        if ($active && !$checkoutEnabled && !$manualCollectionEnabled) {
            throw new InvalidArgumentException('Une méthode active doit être disponible dans au moins un parcours.');
        }
        if ($active && !$allowDeposit && !$allowBalance && !$allowSinglePayment) {
            throw new InvalidArgumentException('Une méthode active doit autoriser au moins un type d’encaissement.');
        }
        if (!$capability['supports_manual_collection'] && $manualCollectionEnabled) {
            throw new InvalidArgumentException('Ce moyen de paiement ne peut pas être encaissé manuellement.');
        }
        if ($code === 'cb_online' && ($manualCollectionEnabled || $allowDeposit || $allowBalance || !$allowSinglePayment)) {
            throw new InvalidArgumentException('La carte en ligne V1 accepte uniquement le paiement unique intégral.');
        }

        if ($active && $checkoutEnabled) {
            $missing = self::providerMissingKeys($capability['provider']);
            if ($missing !== []) {
                throw new ConfigurationIncompleteException($missing, 'payment:' . $code);
            }
        }

        $stmt = Database::getConnection()->prepare(
            'INSERT INTO mode_paiement
                (libelle, code, actif, checkout_enabled, manual_collection_enabled,
                 allow_deposit, allow_balance, allow_single_payment, instructions)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                libelle = VALUES(libelle),
                actif = VALUES(actif),
                checkout_enabled = VALUES(checkout_enabled),
                manual_collection_enabled = VALUES(manual_collection_enabled),
                allow_deposit = VALUES(allow_deposit),
                allow_balance = VALUES(allow_balance),
                allow_single_payment = VALUES(allow_single_payment),
                instructions = VALUES(instructions)',
        );
        $stmt->execute([
            $capability['label'],
            $code,
            $active ? 1 : 0,
            $checkoutEnabled ? 1 : 0,
            $manualCollectionEnabled ? 1 : 0,
            $allowDeposit ? 1 : 0,
            $allowBalance ? 1 : 0,
            $allowSinglePayment ? 1 : 0,
            $instructions !== '' ? $instructions : null,
        ]);
    }

    /** @return array<string,mixed> */
    private static function findPolicy(string $code): array
    {
        self::capability($code);
        foreach (self::tenantPolicies() as $method) {
            if ($method['code'] === $code) {
                return $method;
            }
        }

        throw new RuntimeException('Politique de paiement introuvable.');
    }

    /** @param array<string,mixed> $capability @param array<string,mixed>|null $row @return array<string,mixed> */
    private static function policy(string $code, array $capability, ?array $row): array
    {
        $provider = $capability['provider'];
        $providerMissingKeys = self::providerMissingKeys($provider);

        return [
            'code' => $code,
            'label' => $capability['label'],
            'actif' => (bool) ($row['actif'] ?? false),
            'checkout_enabled' => (bool) ($row['checkout_enabled'] ?? false),
            'manual_collection_enabled' => (bool) ($row['manual_collection_enabled'] ?? false),
            'allow_deposit' => (bool) ($row['allow_deposit'] ?? true),
            'allow_balance' => (bool) ($row['allow_balance'] ?? true),
            'allow_single_payment' => (bool) ($row['allow_single_payment'] ?? true),
            'instructions' => trim((string) ($row['instructions'] ?? '')),
            'provider' => $provider,
            'requires_external_provider' => $provider !== null,
            'checkout_strategy' => $capability['checkout_strategy'],
            'order_status_effect' => $capability['order_status_effect'],
            'supports_manual_collection' => $capability['supports_manual_collection'],
            'provider_ready' => $providerMissingKeys === [],
            'provider_missing_keys' => $providerMissingKeys,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private static function loadRows(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT code, actif, checkout_enabled, manual_collection_enabled,
                    allow_deposit, allow_balance, allow_single_payment, instructions
             FROM mode_paiement',
        );
        $indexed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = (string) ($row['code'] ?? '');
            if (isset(self::CAPABILITIES[$code])) {
                $indexed[$code] = $row;
            }
        }

        return $indexed;
    }

    /** @return array<string,mixed> */
    private static function capability(string $code): array
    {
        $capability = self::CAPABILITIES[$code] ?? null;
        if ($capability === null) {
            throw new InvalidArgumentException('Moyen de paiement non supporté par Tugères V1.');
        }

        return $capability;
    }

    /** @param array<string,mixed> $method */
    private static function allowsPaymentType(array $method, string $paymentType): bool
    {
        return match ($paymentType) {
            'acompte' => (bool) $method['allow_deposit'],
            'solde' => (bool) $method['allow_balance'],
            'paiement_unique' => (bool) $method['allow_single_payment'],
            default => false,
        };
    }

    private static function assertPaymentType(string $paymentType): void
    {
        if (!in_array($paymentType, ['acompte', 'solde', 'paiement_unique'], true)) {
            throw new InvalidArgumentException('Type de paiement invalide.');
        }
    }

    /** @return list<string> */
    private static function providerRequiredKeys(?string $provider): array
    {
        return match ($provider) {
            'stripe' => [
                'operator.stripe.secret_key',
                'operator.stripe.webhook_secret',
                'operator.base_url',
            ],
            null => [],
            default => throw new RuntimeException('Fournisseur de paiement inconnu: ' . $provider),
        };
    }

    /** @return list<string> */
    private static function providerMissingKeys(?string $provider): array
    {
        $missing = [];
        foreach (self::providerRequiredKeys($provider) as $key) {
            try {
                if (!OperatorConfiguration::isConfigured($key)) {
                    $missing[] = $key;
                    continue;
                }

                $value = OperatorConfiguration::string($key);
                if ($value === '' || str_contains($value, 'REMPLACER')) {
                    $missing[] = $key;
                }
            } catch (ConfigurationMissingException|ConfigurationInvalidException) {
                $missing[] = $key;
            }
        }

        sort($missing);
        return $missing;
    }
}
