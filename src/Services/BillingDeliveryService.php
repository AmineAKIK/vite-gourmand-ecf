<?php

declare(strict_types=1);

namespace App\Services;

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
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new InvalidArgumentException('Seuls les documents finalisés peuvent être envoyés.');
        }
        if (trim((string) ($document['client_email'] ?? '')) === '') {
            throw new InvalidArgumentException('Aucune adresse email client disponible pour ce document.');
        }

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

    public static function sendSignatureRequest(int $documentId): void
    {
        $document = FacturationModel::getById($documentId);
        if (!$document) {
            throw new InvalidArgumentException('Document introuvable.');
        }
        if (($document['type_document'] ?? '') !== 'devis') {
            throw new InvalidArgumentException('Seul un devis peut être envoyé pour signature.');
        }
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new InvalidArgumentException('Le devis doit être finalisé avant envoi pour signature.');
        }
        if (trim((string) ($document['client_email'] ?? '')) === '') {
            throw new InvalidArgumentException('Aucune adresse email client disponible pour ce devis.');
        }

        $token = QuoteDecisionService::createSignatureToken($documentId);
        $signatureUrl = rtrim(BASE_URL, '/') . '/devis/accepter?token=' . urlencode($token);
        MailService::sendDevisSignatureRequest($document, $signatureUrl);
    }
}
