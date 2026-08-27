<?php

declare(strict_types=1);

namespace App\Domain;

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

    public static function assertArchiveStatus(?string $status): void
    {
        if ($status !== null && !in_array($status, ['pending', 'ready', 'failed'], true)) {
            throw new InvalidArgumentException('État d’archivage invalide.');
        }
    }
}
