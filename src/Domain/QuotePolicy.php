<?php

declare(strict_types=1);

namespace App\Domain;

use App\Config\Configuration;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class QuotePolicy
{
    public function __construct(private readonly BusinessPolicy $businessPolicy)
    {
    }

    public static function fromConfiguration(): self
    {
        return new self(new BusinessPolicy(static fn(string $key): mixed => Configuration::get($key)));
    }

    public function expiryForEmission(string $dateEmission): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($dateEmission));
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== trim($dateEmission)) {
            throw new InvalidArgumentException('Date d’émission du devis invalide.');
        }

        return $date
            ->modify('+' . $this->businessPolicy->quoteValidityDays() . ' days')
            ->setTime(23, 59, 59);
    }

    public function expiry(?string $persistedExpiry, ?string $dateEmission): DateTimeImmutable
    {
        if ($persistedExpiry !== null && trim($persistedExpiry) !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($persistedExpiry));
            if (!$parsed instanceof DateTimeImmutable || $parsed->format('Y-m-d H:i:s') !== trim($persistedExpiry)) {
                throw new RuntimeException('Expiration du devis invalide.');
            }

            return $parsed;
        }

        if ($dateEmission === null || trim($dateEmission) === '') {
            throw new RuntimeException('Date d’émission du devis manquante.');
        }

        return $this->expiryForEmission($dateEmission);
    }

    public function assertOpen(
        ?string $decision,
        ?string $persistedExpiry,
        ?string $dateEmission,
        ?DateTimeImmutable $now = null,
    ): void {
        if ($decision !== null && trim($decision) !== '') {
            throw new RuntimeException('La décision sur ce devis est déjà définitive.');
        }

        $now ??= new DateTimeImmutable('now');
        if ($now > $this->expiry($persistedExpiry, $dateEmission)) {
            throw new RuntimeException('Ce devis a expiré.');
        }
    }

    /** @param array<string,mixed> $document */
    public function workflowState(array $document, ?DateTimeImmutable $now = null): string
    {
        if (($document['type_document'] ?? '') !== 'devis') {
            throw new InvalidArgumentException('Ce document n’est pas un devis.');
        }

        if (($document['statut'] ?? '') === 'brouillon') {
            return 'brouillon';
        }
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new RuntimeException('État de devis incohérent.');
        }

        $decision = trim((string) ($document['statut_devis'] ?? ''));
        if ($decision === 'accepte') {
            return 'accepte';
        }
        if ($decision === 'refuse') {
            return 'refuse';
        }
        if ($decision !== '') {
            throw new RuntimeException('Décision de devis incohérente.');
        }

        $now ??= new DateTimeImmutable('now');
        if ($now > $this->expiry(
            isset($document['signature_expires_at']) ? (string) $document['signature_expires_at'] : null,
            isset($document['date_emission']) ? (string) $document['date_emission'] : null,
        )) {
            return 'expire';
        }

        if (!empty($document['sent_at'])) {
            return 'envoye';
        }

        return 'finalise';
    }
}
