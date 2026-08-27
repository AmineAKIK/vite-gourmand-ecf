<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\ConfigurationCompleteness;
use App\Models\FacturationModel;
use RuntimeException;
use Throwable;

final class BillingFinalizationService
{
    /**
     * @return array{numero:string, archive:?string, archive_warning:?string}
     */
    public static function finalize(int $documentId, ?int $finalizedBy): array
    {
        ConfigurationCompleteness::assertBillingReady();

        $numero = null;
        $initialArchiveError = null;

        try {
            $numero = FacturationModel::finalizeDraft($documentId, $finalizedBy);
        } catch (Throwable $e) {
            // Le modèle historique archive après le COMMIT. Si cette écriture fichier échoue,
            // le document est tout de même juridiquement finalisé et numéroté en base.
            $document = FacturationModel::getById($documentId);
            if (!$document
                || ($document['statut'] ?? '') !== 'finalise'
                || trim((string) ($document['numero_document'] ?? '')) === ''
            ) {
                throw $e;
            }

            $numero = (string) $document['numero_document'];
            $initialArchiveError = $e->getMessage();
        }

        if ($numero === null || $numero === '') {
            throw new RuntimeException('Finalisation du document incohérente.');
        }

        // A finalized document must have an immutable canonical snapshot before it
        // can be delivered or exposed as an accounting artifact. Snapshot failure
        // is therefore fatal and never downgraded to an archive warning.
        BillingFinalizedSnapshotService::capture($documentId, $finalizedBy);

        try {
            $archive = BillingDocumentStorage::ensureArchive($documentId);
            return [
                'numero' => $numero,
                'archive' => $archive,
                'archive_warning' => null,
            ];
        } catch (Throwable $e) {
            BillingDocumentStorage::markArchiveFailed($documentId, $e->getMessage());
            $message = $initialArchiveError !== null
                ? $initialArchiveError . ' / ' . $e->getMessage()
                : $e->getMessage();

            error_log(sprintf(
                '[facturation] document finalisé mais archive en échec document_id=%d: %s',
                $documentId,
                $message,
            ));

            return [
                'numero' => $numero,
                'archive' => null,
                'archive_warning' => 'Document finalisé, mais archive indisponible. Une nouvelle tentative sera faite au prochain accès.',
            ];
        }
    }
}
