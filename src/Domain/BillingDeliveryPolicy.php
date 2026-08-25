<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final class BillingDeliveryPolicy
{
    public static function assertCanSend(array $document): void
    {
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new InvalidArgumentException('Seuls les documents finalisés peuvent être envoyés.');
        }
        if (trim((string) ($document['client_email'] ?? '')) === '') {
            throw new InvalidArgumentException('Aucune adresse email client disponible pour ce document.');
        }
    }

    public static function assertCanRequestSignature(array $document): void
    {
        if (($document['type_document'] ?? '') !== 'devis') {
            throw new InvalidArgumentException('Seul un devis peut être envoyé pour signature.');
        }
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new InvalidArgumentException('Le devis doit être finalisé avant envoi pour signature.');
        }
        if (trim((string) ($document['client_email'] ?? '')) === '') {
            throw new InvalidArgumentException('Aucune adresse email client disponible pour ce devis.');
        }
    }
}
