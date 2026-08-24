<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class BillingDocumentPolicy
{
    public static function assertDraft(string $status): void
    {
        if ($status !== 'brouillon') {
            throw new RuntimeException('Seuls les brouillons peuvent être modifiés ou finalisés.');
        }
    }

    public static function assertQuoteOpen(?string $decision, ?string $expiresAt, ?string $dateEmission, ?DateTimeImmutable $now = null): void
    {
        if ($decision !== null && $decision !== '') {
            throw new RuntimeException('La décision sur ce devis est déjà définitive.');
        }

        $now ??= new DateTimeImmutable('now');
        $expiry = self::quoteExpiry($expiresAt, $dateEmission);
        if ($expiry !== null && $now >= $expiry) {
            throw new RuntimeException('Ce devis a expiré.');
        }
    }

    public static function quoteExpiry(?string $expiresAt, ?string $dateEmission): ?DateTimeImmutable
    {
        if ($expiresAt !== null && trim($expiresAt) !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiresAt);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        if ($dateEmission === null || trim($dateEmission) === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateEmission);
        return $date instanceof DateTimeImmutable ? $date->modify('+30 days') : null;
    }

    public static function signatureExpiry(string $dateEmission): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateEmission);
        if (!$date instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Date d’émission du devis invalide.');
        }

        return $date->modify('+30 days')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    }

    public static function assertArchiveStatus(?string $status): void
    {
        if ($status !== null && !in_array($status, ['pending', 'ready', 'failed'], true)) {
            throw new InvalidArgumentException('État d’archivage invalide.');
        }
    }
}
