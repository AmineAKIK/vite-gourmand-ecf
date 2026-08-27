<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\BillingDeliveryPolicy;
use App\Models\CommandeModel;
use App\Models\FacturationModel;
use InvalidArgumentException;

final class BillingDeliveryService
{
    /**
     * @return array{document:array,archive:string}
     */
    public static function sendFinalized(int $documentId, ?int $sentBy): array
    {
        $document = FacturationModel::getById($documentId);
        if (!$document) {
            throw new InvalidArgumentException('Document introuvable.');
        }
        BillingDeliveryPolicy::assertCanSend($document);

        $archiveAbsolute = BillingDocumentStorage::ensureArchive($documentId);
        $document = FacturationModel::getById($documentId) ?: $document;
        $commande = CommandeModel::getById((int) $document['commande_id']);

        if (($document['type_document'] ?? '') === 'devis') {
            MailService::sendDevis($document, $commande ?: [], $archiveAbsolute);
        } else {
            MailService::sendDocumentFacturation($document, $commande ?: [], $archiveAbsolute);
        }

        BillingDocumentStorage::migrateExisting($documentId);
        FacturationModel::markSent($documentId, $sentBy);

        return [
            'document' => $document,
            'archive' => $archiveAbsolute,
        ];
    }

    public static function sendSignatureRequest(int $documentId, ?int $sentBy = null): void
    {
        $document = FacturationModel::getById($documentId);
        if (!$document) {
            throw new InvalidArgumentException('Document introuvable.');
        }
        BillingDeliveryPolicy::assertCanRequestSignature($document);

        $token = QuoteDecisionService::createSignatureToken($documentId);
        $signatureUrl = rtrim(BASE_URL, '/') . '/devis/accepter?token=' . urlencode($token);
        MailService::sendDevisSignatureRequest($document, $signatureUrl);
        FacturationModel::markSent($documentId, $sentBy);
    }
}
