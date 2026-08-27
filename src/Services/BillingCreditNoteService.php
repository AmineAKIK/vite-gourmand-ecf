<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class BillingCreditNoteService
{
    /**
     * @return list<int>
     */
    public static function createForCancellation(PDO $db, int $commandeId, ?int $createdBy): array
    {
        if (!$db->inTransaction()) {
            throw new RuntimeException('La création des avoirs doit être transactionnelle.');
        }

        $stmt = $db->prepare(
            "SELECT * FROM document_facturation
             WHERE commande_id = ?
               AND statut = 'finalise'
               AND type_document IN ('facture', 'acompte')
             ORDER BY finalized_at ASC, document_id ASC
             FOR UPDATE",
        );
        $stmt->execute([$commandeId]);
        $sources = $stmt->fetchAll();

        // Une facture finale porte déjà le total de la commande et affiche l'acompte versé.
        // L'annuler en plus de la facture d'acompte créerait un double avoir. Si une facture
        // finale existe, elle est donc le document fiscal de référence ; sinon on annule
        // uniquement la dernière facture d'acompte disponible.
        $factures = array_values(array_filter(
            $sources,
            static fn(array $source): bool => ($source['type_document'] ?? '') === 'facture',
        ));
        if ($factures !== []) {
            $sources = [end($factures)];
        } else {
            $acomptes = array_values(array_filter(
                $sources,
                static fn(array $source): bool => ($source['type_document'] ?? '') === 'acompte',
            ));
            $sources = $acomptes === [] ? [] : [end($acomptes)];
        }

        $created = [];
        foreach ($sources as $source) {
            $sourceId = (int) $source['document_id'];
            $existing = $db->prepare(
                "SELECT document_id FROM document_facturation
                 WHERE source_document_id = ? AND type_document = 'avoir'
                 LIMIT 1 FOR UPDATE",
            );
            $existing->execute([$sourceId]);
            $existingId = $existing->fetchColumn();
            if ($existingId !== false) {
                $creditId = (int) $existingId;
                BillingFinalizedSnapshotService::captureInTransaction($db, $creditId, $createdBy);
                $created[] = $creditId;
                continue;
            }

            $numero = self::nextCreditNumber($db, (string) ($source['date_emission'] ?? date('Y-m-d')));
            $insert = $db->prepare(
                "INSERT INTO document_facturation (
                    commande_id, type_document, statut, numero_document, date_emission, date_prestation,
                    client_nom, client_email, client_telephone, client_adresse, client_ville,
                    client_code_postal, client_siren, adresse_livraison, ville_livraison,
                    code_postal_livraison, categorie_operation, option_tva_debits,
                    entreprise_snapshot, note_publique, mention_legale,
                    total_ht, total_tva, total_ttc, created_by, finalized_at, finalized_by,
                    source_document_id, archive_status
                 ) VALUES (?, 'avoir', 'finalise', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'pending')",
            );
            $insert->execute([
                $commandeId,
                $numero,
                date('Y-m-d'),
                $source['date_prestation'] ?? null,
                $source['client_nom'] ?? '',
                $source['client_email'] ?? '',
                $source['client_telephone'] ?? '',
                $source['client_adresse'] ?? '',
                $source['client_ville'] ?? '',
                $source['client_code_postal'] ?? '',
                $source['client_siren'] ?? null,
                $source['adresse_livraison'] ?? null,
                $source['ville_livraison'] ?? null,
                $source['code_postal_livraison'] ?? null,
                $source['categorie_operation'] ?? 'mixte',
                (int) ($source['option_tva_debits'] ?? 0),
                $source['entreprise_snapshot'] ?? null,
                'Avoir intégral suite à annulation de la commande.',
                'Avoir annulant le document ' . (string) ($source['numero_document'] ?? ('#' . $sourceId)),
                -abs((float) ($source['total_ht'] ?? 0)),
                -abs((float) ($source['total_tva'] ?? 0)),
                -abs((float) ($source['total_ttc'] ?? 0)),
                $createdBy,
                $createdBy,
                $sourceId,
            ]);
            $creditId = (int) $db->lastInsertId();

            $lines = $db->prepare(
                'SELECT * FROM document_facturation_ligne WHERE document_id = ? ORDER BY ordre, ligne_document_id',
            );
            $lines->execute([$sourceId]);
            $insertLine = $db->prepare(
                'INSERT INTO document_facturation_ligne (
                    document_id, designation, quantite, prix_unitaire_ht, prix_unitaire_ttc,
                    taux_tva, total_ht, total_tva, total_ttc, ordre
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            );
            foreach ($lines->fetchAll() as $line) {
                $insertLine->execute([
                    $creditId,
                    'Avoir — ' . (string) ($line['designation'] ?? ''),
                    (float) ($line['quantite'] ?? 1),
                    -abs((float) ($line['prix_unitaire_ht'] ?? 0)),
                    -abs((float) ($line['prix_unitaire_ttc'] ?? 0)),
                    (float) ($line['taux_tva'] ?? 0),
                    -abs((float) ($line['total_ht'] ?? 0)),
                    -abs((float) ($line['total_tva'] ?? 0)),
                    -abs((float) ($line['total_ttc'] ?? 0)),
                    (int) ($line['ordre'] ?? 0),
                ]);
            }

            BillingFinalizedSnapshotService::captureInTransaction($db, $creditId, $createdBy);
            $created[] = $creditId;
        }

        return $created;
    }

    private static function nextCreditNumber(PDO $db, string $dateEmission): string
    {
        $timestamp = strtotime($dateEmission) ?: time();
        $year = (int) date('Y', $timestamp);
        $type = 'avoir';

        $stmt = $db->prepare(
            'SELECT dernier_numero FROM document_sequence WHERE type_document = ? AND annee = ? FOR UPDATE',
        );
        $stmt->execute([$type, $year]);
        $current = $stmt->fetchColumn();
        $next = $current === false ? 1 : ((int) $current + 1);

        if ($current === false) {
            $db->prepare(
                'INSERT INTO document_sequence (type_document, annee, dernier_numero) VALUES (?, ?, ?)',
            )->execute([$type, $year, $next]);
        } else {
            $db->prepare(
                'UPDATE document_sequence SET dernier_numero = ? WHERE type_document = ? AND annee = ?',
            )->execute([$next, $type, $year]);
        }

        return sprintf('AVR-%d-%04d', $year, $next);
    }
}
