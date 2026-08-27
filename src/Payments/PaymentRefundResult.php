<?php

declare(strict_types=1);

namespace App\Payments;

use InvalidArgumentException;

final readonly class PaymentRefundResult
{
    public function __construct(
        public string $id,
        public string $status,
    ) {
        if (trim($this->id) === '' || !in_array($this->status, ['pending', 'succeeded', 'failed'], true)) {
            throw new InvalidArgumentException('Résultat de remboursement fournisseur invalide.');
        }
    }
}
