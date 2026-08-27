<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Configuration;
use App\Domain\BusinessPolicy;
use App\Domain\DeliveryPolicy;
use App\Domain\Money;
use InvalidArgumentException;

final class TermsAndConditionsService
{
    /**
     * @param callable(string):mixed $resolve
     * @param callable():list<array<string,mixed>> $paymentPolicies
     */
    public function __construct(
        private readonly BusinessPolicy $businessPolicy,
        private readonly DeliveryPolicy $deliveryPolicy,
        private readonly mixed $resolve,
        private readonly mixed $paymentPolicies,
    ) {
        if (!is_callable($this->resolve) || !is_callable($this->paymentPolicies)) {
            throw new InvalidArgumentException('TermsAndConditionsService dependencies must be callable.');
        }
    }

    public static function fromConfiguration(): self
    {
        $resolve = static fn(string $key): mixed => Configuration::get($key);

        return new self(
            new BusinessPolicy($resolve),
            DeliveryPolicy::fromConfiguration(),
            $resolve,
            static fn(): array => PaymentMethodRegistry::tenantPolicies(),
        );
    }

    /**
     * @return array{
     *   seller:list<string>,
     *   sections:list<array{title:string,paragraphs:list<string>,items:list<string>}>,
     *   explicit_content:string
     * }
     */
    public function build(): array
    {
        $sections = [
            $this->orderingSection(),
            $this->cancellationSection(),
            $this->deliverySection(),
            $this->paymentMethodsSection(),
            $this->materialSection(),
        ];

        $paymentTerms = $this->paymentTermsSection();
        if ($paymentTerms !== null) {
            $sections[] = $paymentTerms;
        }

        return [
            'seller' => $this->sellerLines(),
            'sections' => $sections,
            'explicit_content' => $this->optionalString('legal.terms_content'),
        ];
    }

    /** @return list<string> */
    private function sellerLines(): array
    {
        $legalName = $this->requiredString('business.legal_name');
        $legalForm = $this->optionalString('business.legal_form');
        $siret = $this->requiredString('business.siret');
        $address = implode(', ', [
            $this->requiredString('business.address.line1'),
            $this->requiredString('business.address.postal_code') . ' ' . $this->requiredString('business.address.city'),
        ]);

        $identity = $legalName . ($legalForm !== '' ? ' — ' . $legalForm : '');
        $lines = [
            $identity,
            'SIRET : ' . $siret,
            'Adresse : ' . $address,
            'Email : ' . $this->requiredString('business.email'),
        ];

        $phone = $this->optionalString('business.phone');
        if ($phone !== '') {
            $lines[] = 'Téléphone : ' . $phone;
        }

        $vatNumber = $this->optionalString('business.vat_number');
        if ($vatNumber !== '') {
            $lines[] = 'TVA intracommunautaire : ' . $vatNumber;
        }

        return $lines;
    }

    /** @return array{title:string,paragraphs:list<string>,items:list<string>} */
    private function orderingSection(): array
    {
        return [
            'title' => 'Commande et devis',
            'paragraphs' => [
                sprintf(
                    'Toute prestation commandée via le site doit être planifiée au minimum %d heure(s) à l’avance et au maximum %d jour(s) à l’avance.',
                    $this->businessPolicy->minimumOrderLeadHours(),
                    $this->businessPolicy->maximumOrderAdvanceDays(),
                ),
                sprintf(
                    'Un devis reste valable %d jour(s) à compter de son émission.',
                    $this->businessPolicy->quoteValidityDays(),
                ),
            ],
            'items' => [],
        ];
    }

    /** @return array{title:string,paragraphs:list<string>,items:list<string>} */
    private function cancellationSection(): array
    {
        return [
            'title' => 'Annulation et remboursement',
            'paragraphs' => [
                sprintf(
                    'La demande d’annulation est admise jusqu’à %d heure(s) avant la prestation. Une annulation effectuée dans ce délai ouvre droit au remboursement intégral des sommes encaissées ; au-delà, le workflow d’annulation financière est bloqué.',
                    $this->businessPolicy->customerCancellationCutoffHours(),
                ),
                'Un remboursement fournisseur est confirmé avant que la commande ne soit marquée annulée. Le système interdit tout remboursement supérieur aux sommes effectivement encaissées.',
            ],
            'items' => [],
        ];
    }

    /** @return array{title:string,paragraphs:list<string>,items:list<string>} */
    private function deliverySection(): array
    {
        return [
            'title' => 'Livraison',
            'paragraphs' => [$this->deliveryPolicy->pricingLabel()],
            'items' => [],
        ];
    }

    /** @return array{title:string,paragraphs:list<string>,items:list<string>} */
    private function paymentMethodsSection(): array
    {
        $items = [];
        foreach (($this->paymentPolicies)() as $method) {
            if (!is_array($method) || empty($method['actif'])) {
                continue;
            }

            $journeys = [];
            if (!empty($method['checkout_enabled']) && !empty($method['provider_ready'])) {
                $journeys[] = 'commande en ligne';
            }
            if (!empty($method['manual_collection_enabled']) && !empty($method['supports_manual_collection'])) {
                $journeys[] = 'encaissement par l’équipe';
            }
            if ($journeys === []) {
                continue;
            }

            $rules = [];
            if (!empty($method['allow_deposit'])) {
                $rules[] = 'acompte';
            }
            if (!empty($method['allow_balance'])) {
                $rules[] = 'solde';
            }
            if (!empty($method['allow_single_payment'])) {
                $rules[] = 'paiement unique';
            }

            $line = sprintf(
                '%s — disponible pour : %s',
                trim((string) ($method['label'] ?? $method['code'] ?? 'Moyen de paiement')),
                implode(', ', $journeys),
            );
            if ($rules !== []) {
                $line .= ' ; encaissements autorisés : ' . implode(', ', $rules);
            }

            $instructions = trim((string) ($method['instructions'] ?? ''));
            if ($instructions !== '') {
                $line .= ' ; instructions : ' . $instructions;
            }

            $items[] = $line . '.';
        }

        return [
            'title' => 'Moyens de paiement',
            'paragraphs' => $items === []
                ? ['Aucun moyen de paiement n’est actuellement disponible dans un parcours de vente actif.']
                : [],
            'items' => $items,
        ];
    }

    /** @return array{title:string,paragraphs:list<string>,items:list<string>} */
    private function materialSection(): array
    {
        $returnDays = $this->businessPolicy->materialReturnDays();
        $lateFeeCents = $this->businessPolicy->materialLateFeeCents();

        $paragraph = sprintf(
            'Lorsqu’un matériel est confié au client, il doit être restitué dans un délai de %d jour(s) après la prestation.',
            $returnDays,
        );
        if ($lateFeeCents > 0) {
            $paragraph .= sprintf(
                ' La pénalité configurée en cas de retard est de %s €.',
                Money::toDecimalString($lateFeeCents),
            );
        }

        return [
            'title' => 'Matériel confié',
            'paragraphs' => [$paragraph],
            'items' => [],
        ];
    }

    /** @return array{title:string,paragraphs:list<string>,items:list<string>}|null */
    private function paymentTermsSection(): ?array
    {
        $items = [];

        $depositRate = ($this->resolve)('payment.deposit.default_rate_percent');
        if (is_int($depositRate)) {
            $items[] = sprintf('Taux d’acompte par défaut lorsqu’un acompte est demandé : %d %%.', $depositRate);
        }

        $termsDays = ($this->resolve)('payment.terms_days');
        if (is_int($termsDays)) {
            $items[] = sprintf('Délai de paiement configuré : %d jour(s).', $termsDays);
        }

        $lateFeeRate = ($this->resolve)('payment.late_fee_rate_percent');
        if (is_int($lateFeeRate) || is_float($lateFeeRate) || is_string($lateFeeRate)) {
            $lateFeeRate = trim((string) $lateFeeRate);
            if ($lateFeeRate !== '') {
                $items[] = 'Taux de pénalités de retard configuré : ' . $lateFeeRate . ' %.';
            }
        }

        $recoveryFee = ($this->resolve)('payment.recovery_fee');
        if (is_string($recoveryFee) && $recoveryFee !== '') {
            $items[] = 'Indemnité forfaitaire de recouvrement configurée : '
                . Money::toDecimalString(Money::fromDecimal($recoveryFee)) . ' €.';
        }

        if ($items === []) {
            return null;
        }

        return [
            'title' => 'Conditions de paiement',
            'paragraphs' => [],
            'items' => $items,
        ];
    }

    private function requiredString(string $key): string
    {
        $value = ($this->resolve)($key);
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Configuration requise pour les CGV : ' . $key);
        }

        return trim($value);
    }

    private function optionalString(string $key): string
    {
        $value = ($this->resolve)($key);
        return is_string($value) ? trim($value) : '';
    }
}
