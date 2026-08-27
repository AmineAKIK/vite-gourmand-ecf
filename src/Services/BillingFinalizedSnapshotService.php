<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\Money;
use PDO;
use RuntimeException;
use Throwable;

final class BillingFinalizedSnapshotService
{
    public static function capture(int $documentId, ?int $finalizedBy): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            self::captureInTransaction($db, $documentId, $finalizedBy);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function captureInTransaction(PDO $db, int $documentId, ?int $finalizedBy): void
    {
        if (!$db->inTransaction()) {
            throw new RuntimeException('Le snapshot final doit être créé dans une transaction.');
        }

        $existing = $db->prepare(
            'SELECT document_id FROM billing_document_finalized_snapshot WHERE document_id = ? FOR UPDATE',
        );
        $existing->execute([$documentId]);
        if ($existing->fetchColumn() !== false) {
            self::ensureGuard($db, $documentId);
            return;
        }

        $stmt = $db->prepare('SELECT * FROM document_facturation WHERE document_id = ? FOR UPDATE');
        $stmt->execute([$documentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($document)) {
            throw new RuntimeException('Document de facturation introuvable.');
        }
        if (($document['statut'] ?? '') !== 'finalise' || trim((string) ($document['numero_document'] ?? '')) === '') {
            throw new RuntimeException('Seul un document numéroté et finalisé peut être scellé.');
        }

        $linesStmt = $db->prepare(
            'SELECT * FROM document_facturation_ligne WHERE document_id = ? ORDER BY ordre, ligne_document_id FOR UPDATE',
        );
        $linesStmt->execute([$documentId]);
        $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($lines === []) {
            throw new RuntimeException('Un document finalisé doit contenir au moins une ligne.');
        }

        $payload = self::payload($document, $lines);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $finalizedAt = trim((string) ($document['finalized_at'] ?? ''));
        if ($finalizedAt === '') {
            throw new RuntimeException('Date de finalisation manquante.');
        }

        $insert = $db->prepare(
            'INSERT INTO billing_document_finalized_snapshot
                (document_id, schema_version, snapshot_payload, finalized_at, finalized_by)
             VALUES (?, 1, ?, ?, ?)',
        );
        $insert->execute([$documentId, $json, $finalizedAt, $document['finalized_by'] ?? $finalizedBy]);
        self::ensureGuard($db, $documentId);
    }

    /** @return array<string,mixed> */
    public static function canonicalDocument(int $documentId): array
    {
        $db = Database::getConnection();
        $liveStmt = $db->prepare('SELECT * FROM document_facturation WHERE document_id = ?');
        $liveStmt->execute([$documentId]);
        $live = $liveStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($live)) {
            throw new RuntimeException('Document de facturation introuvable.');
        }
        if (($live['statut'] ?? '') !== 'finalise') {
            throw new RuntimeException('Seuls les documents finalisés ont un snapshot canonique.');
        }

        $snapshot = self::snapshotPayload($db, $documentId);
        if ($snapshot === null) {
            self::capture($documentId, isset($live['finalized_by']) ? (int) $live['finalized_by'] : null);
            $snapshot = self::snapshotPayload($db, $documentId);
        }
        if ($snapshot === null) {
            throw new RuntimeException('Snapshot final de facturation introuvable.');
        }

        return self::legacyDocument($snapshot, $live);
    }

    /** @return array<string,mixed>|null */
    private static function snapshotPayload(PDO $db, int $documentId): ?array
    {
        $stmt = $db->prepare(
            'SELECT s.snapshot_payload
             FROM billing_document_finalized_snapshot s
             INNER JOIN billing_document_snapshot_guard g
                ON g.document_id = s.document_id AND g.payload_hash = s.payload_hash
             WHERE s.document_id = ?',
        );
        $stmt->execute([$documentId]);
        $json = $stmt->fetchColumn();
        if (!is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    }

    private static function ensureGuard(PDO $db, int $documentId): void
    {
        $stmt = $db->prepare(
            'INSERT INTO billing_document_snapshot_guard (document_id, payload_hash)
             SELECT document_id, payload_hash
             FROM billing_document_finalized_snapshot
             WHERE document_id = ?
             ON DUPLICATE KEY UPDATE document_id = VALUES(document_id)',
        );
        $stmt->execute([$documentId]);
        if ($stmt->rowCount() === 0) {
            $check = $db->prepare('SELECT 1 FROM billing_document_snapshot_guard WHERE document_id = ?');
            $check->execute([$documentId]);
            if ($check->fetchColumn() === false) {
                throw new RuntimeException('Impossible de verrouiller le snapshot final.');
            }
        }
    }

    /** @param array<string,mixed> $document @param list<array<string,mixed>> $lines */
    private static function payload(array $document, array $lines): array
    {
        $lineSnapshots = [];
        foreach ($lines as $line) {
            $lineSnapshots[] = [
                'id' => (int) $line['ligne_document_id'],
                'designation' => (string) $line['designation'],
                'quantity' => (string) $line['quantite'],
                'unit_ht_cents' => Money::fromDecimal((string) $line['prix_unitaire_ht']),
                'unit_ttc_cents' => Money::fromDecimal((string) $line['prix_unitaire_ttc']),
                'vat_rate' => (string) $line['taux_tva'],
                'vat_rate_id' => $line['taux_tva_id'] === null ? null : (int) $line['taux_tva_id'],
                'total_ht_cents' => Money::fromDecimal((string) $line['total_ht']),
                'total_vat_cents' => Money::fromDecimal((string) $line['total_tva']),
                'total_ttc_cents' => Money::fromDecimal((string) $line['total_ttc']),
                'order' => (int) $line['ordre'],
            ];
        }

        return [
            'schema_version' => 1,
            'document' => [
                'id' => (int) $document['document_id'],
                'order_id' => (int) $document['commande_id'],
                'type' => (string) $document['type_document'],
                'status' => (string) $document['statut'],
                'number' => (string) $document['numero_document'],
                'issued_on' => (string) $document['date_emission'],
                'service_on' => $document['date_prestation'] === null ? null : (string) $document['date_prestation'],
            ],
            'client' => [
                'name' => (string) $document['client_nom'],
                'email' => (string) $document['client_email'],
                'phone' => (string) $document['client_telephone'],
                'address' => (string) $document['client_adresse'],
                'city' => (string) $document['client_ville'],
                'postal_code' => (string) $document['client_code_postal'],
                'siren' => $document['client_siren'] === null ? null : (string) $document['client_siren'],
            ],
            'delivery' => [
                'address' => $document['adresse_livraison'] === null ? null : (string) $document['adresse_livraison'],
                'city' => $document['ville_livraison'] === null ? null : (string) $document['ville_livraison'],
                'postal_code' => $document['code_postal_livraison'] === null ? null : (string) $document['code_postal_livraison'],
            ],
            'tax' => [
                'operation_category' => (string) $document['categorie_operation'],
                'vat_on_debits' => (int) $document['option_tva_debits'] === 1,
            ],
            'business_snapshot' => $document['entreprise_snapshot'],
            'content' => [
                'public_note' => $document['note_publique'],
                'legal_notice' => $document['mention_legale'],
            ],
            'money' => [
                'currency' => 'EUR',
                'total_ht_cents' => Money::fromDecimal((string) $document['total_ht']),
                'total_vat_cents' => Money::fromDecimal((string) $document['total_tva']),
                'total_ttc_cents' => Money::fromDecimal((string) $document['total_ttc']),
                'deposit_paid_cents' => Money::fromDecimal((string) $document['montant_acompte_verse']),
                'balance_due_cents' => Money::fromDecimal((string) $document['solde_a_regler']),
            ],
            'links' => [
                'deposit_document_id' => $document['document_acompte_id'] === null ? null : (int) $document['document_acompte_id'],
                'source_document_id' => $document['source_document_id'] === null ? null : (int) $document['source_document_id'],
            ],
            'finalization' => [
                'at' => (string) $document['finalized_at'],
                'by' => $document['finalized_by'] === null ? null : (int) $document['finalized_by'],
            ],
            'lines' => $lineSnapshots,
        ];
    }

    /** @param array<string,mixed> $snapshot @param array<string,mixed> $live */
    private static function legacyDocument(array $snapshot, array $live): array
    {
        $document = is_array($snapshot['document'] ?? null) ? $snapshot['document'] : [];
        $client = is_array($snapshot['client'] ?? null) ? $snapshot['client'] : [];
        $delivery = is_array($snapshot['delivery'] ?? null) ? $snapshot['delivery'] : [];
        $tax = is_array($snapshot['tax'] ?? null) ? $snapshot['tax'] : [];
        $content = is_array($snapshot['content'] ?? null) ? $snapshot['content'] : [];
        $money = is_array($snapshot['money'] ?? null) ? $snapshot['money'] : [];
        $links = is_array($snapshot['links'] ?? null) ? $snapshot['links'] : [];
        $finalization = is_array($snapshot['finalization'] ?? null) ? $snapshot['finalization'] : [];

        $canonical = $live;
        $canonical['document_id'] = (int) ($document['id'] ?? $live['document_id']);
        $canonical['commande_id'] = (int) ($document['order_id'] ?? $live['commande_id']);
        $canonical['type_document'] = (string) ($document['type'] ?? $live['type_document']);
        $canonical['statut'] = 'finalise';
        $canonical['numero_document'] = (string) ($document['number'] ?? $live['numero_document']);
        $canonical['date_emission'] = (string) ($document['issued_on'] ?? $live['date_emission']);
        $canonical['date_prestation'] = $document['service_on'] ?? null;
        $canonical['client_nom'] = (string) ($client['name'] ?? '');
        $canonical['client_email'] = (string) ($client['email'] ?? '');
        $canonical['client_telephone'] = (string) ($client['phone'] ?? '');
        $canonical['client_adresse'] = (string) ($client['address'] ?? '');
        $canonical['client_ville'] = (string) ($client['city'] ?? '');
        $canonical['client_code_postal'] = (string) ($client['postal_code'] ?? '');
        $canonical['client_siren'] = $client['siren'] ?? null;
        $canonical['adresse_livraison'] = $delivery['address'] ?? null;
        $canonical['ville_livraison'] = $delivery['city'] ?? null;
        $canonical['code_postal_livraison'] = $delivery['postal_code'] ?? null;
        $canonical['categorie_operation'] = (string) ($tax['operation_category'] ?? 'mixte');
        $canonical['option_tva_debits'] = !empty($tax['vat_on_debits']) ? 1 : 0;
        $canonical['entreprise_snapshot'] = $snapshot['business_snapshot'] ?? null;
        $business = json_decode((string) ($canonical['entreprise_snapshot'] ?? '{}'), true);
        $canonical['entreprise'] = is_array($business) ? $business : [];
        $canonical['note_publique'] = $content['public_note'] ?? null;
        $canonical['mention_legale'] = $content['legal_notice'] ?? null;
        $canonical['total_ht'] = Money::toDecimalString((int) ($money['total_ht_cents'] ?? 0));
        $canonical['total_tva'] = Money::toDecimalString((int) ($money['total_vat_cents'] ?? 0));
        $canonical['total_ttc'] = Money::toDecimalString((int) ($money['total_ttc_cents'] ?? 0));
        $canonical['montant_acompte_verse'] = Money::toDecimalString((int) ($money['deposit_paid_cents'] ?? 0));
        $canonical['solde_a_regler'] = Money::toDecimalString((int) ($money['balance_due_cents'] ?? 0));
        $canonical['document_acompte_id'] = $links['deposit_document_id'] ?? null;
        $canonical['source_document_id'] = $links['source_document_id'] ?? null;
        $canonical['finalized_at'] = $finalization['at'] ?? $live['finalized_at'];
        $canonical['finalized_by'] = $finalization['by'] ?? $live['finalized_by'];
        $canonical['snapshot_schema_version'] = (int) ($snapshot['schema_version'] ?? 1);
        $canonical['snapshot_currency'] = (string) ($money['currency'] ?? 'EUR');
        $canonical['lignes'] = [];

        foreach (is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $canonical['lignes'][] = [
                'ligne_document_id' => (int) ($line['id'] ?? 0),
                'document_id' => $canonical['document_id'],
                'designation' => (string) ($line['designation'] ?? ''),
                'quantite' => (string) ($line['quantity'] ?? '0.00'),
                'prix_unitaire_ht' => Money::toDecimalString((int) ($line['unit_ht_cents'] ?? 0)),
                'prix_unitaire_ttc' => Money::toDecimalString((int) ($line['unit_ttc_cents'] ?? 0)),
                'taux_tva' => (string) ($line['vat_rate'] ?? '0.00'),
                'taux_tva_id' => $line['vat_rate_id'] ?? null,
                'total_ht' => Money::toDecimalString((int) ($line['total_ht_cents'] ?? 0)),
                'total_tva' => Money::toDecimalString((int) ($line['total_vat_cents'] ?? 0)),
                'total_ttc' => Money::toDecimalString((int) ($line['total_ttc_cents'] ?? 0)),
                'ordre' => (int) ($line['order'] ?? 0),
            ];
        }

        return $canonical;
    }
}
