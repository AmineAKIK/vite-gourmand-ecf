<?php

declare(strict_types=1);

namespace App\Payments;

use InvalidArgumentException;

final class PaymentCheckoutRequest
{
    /**
     * @param list<array{name:string,amount_cents:int}> $items
     */
    public function __construct(
        public readonly int $attemptId,
        public readonly int $draftId,
        public readonly string $orderReference,
        public readonly int $userId,
        public readonly int $expectedAmountCents,
        public readonly string $currency,
        public readonly int $expiresAt,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly array $items,
        public readonly int $discountCents = 0,
    ) {
        $currency = strtolower($this->currency);
        if ($this->attemptId <= 0 || $this->draftId <= 0 || $this->userId <= 0) {
            throw new InvalidArgumentException('Identifiants de paiement invalides.');
        }
        if (trim($this->orderReference) === '' || $this->expectedAmountCents <= 0) {
            throw new InvalidArgumentException('Référence ou montant de paiement invalide.');
        }
        if (!preg_match('/^[a-z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Devise de paiement invalide.');
        }
        if ($this->expiresAt <= time()) {
            throw new InvalidArgumentException('Expiration de paiement invalide.');
        }
        self::assertHttpUrl($this->successUrl);
        self::assertHttpUrl($this->cancelUrl);
        if ($this->items === []) {
            throw new InvalidArgumentException('Le checkout doit contenir au moins une ligne.');
        }

        $gross = 0;
        foreach ($this->items as $item) {
            if (trim((string) ($item['name'] ?? '')) === '' || (int) ($item['amount_cents'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Ligne de checkout invalide.');
            }
            $gross += (int) $item['amount_cents'];
        }

        if ($this->discountCents < 0 || $this->discountCents > $gross) {
            throw new InvalidArgumentException('Réduction de checkout invalide.');
        }
        if ($gross - $this->discountCents !== $this->expectedAmountCents) {
            throw new InvalidArgumentException('Le checkout ne converge pas vers le montant attendu.');
        }
    }

    public function normalizedCurrency(): string
    {
        return strtolower($this->currency);
    }

    private static function assertHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('URL de retour paiement invalide.');
        }
    }
}
