<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\QuotePolicy;
use PDO;
use RuntimeException;
use Throwable;

final class QuoteDecisionService
{
    public static function createSignatureToken(int $documentId): string
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $document = self::lockDocumentById($db, $documentId);
            self::assertFinalizedQuote($document);
            $policy = QuotePolicy::fromConfiguration();
            $policy->assertOpen(
                $document['statut_devis'] ?? null,
                $document['signature_expires_at'] ?? null,
                $document['date_emission'] ?? null,
            );

            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expiresAt = $policy->expiry(
                $document['signature_expires_at'] ?? null,
                $document['date_emission'] ?? null,
            )->format('Y-m-d H:i:s');

            $stmt = $db->prepare(
                'UPDATE document_facturation
                 SET token_signature = NULL, signature_token_hash = ?, signature_expires_at = ?
                 WHERE document_id = ?',
            );
            $stmt->execute([$hash, $expiresAt, $documentId]);
            $db->commit();

            return $token;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function findByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM document_facturation
             WHERE type_document = 'devis' AND statut = 'finalise'
               AND (signature_token_hash = ? OR token_signature = ?)
             LIMIT 1",
        );
        $stmt->execute([hash('sha256', $token), $token]);
        $document = $stmt->fetch();
        if (!$document) {
            return null;
        }

        try {
            QuotePolicy::fromConfiguration()->assertOpen(
                $document['statut_devis'] ?? null,
                $document['signature_expires_at'] ?? null,
                $document['date_emission'] ?? null,
            );
        } catch (RuntimeException) {
            if (($document['statut_devis'] ?? null) !== 'accepte') {
                return null;
            }
        }

        $document['workflow_state'] = QuotePolicy::fromConfiguration()->workflowState($document);
        return $document;
    }

    public static function acceptWithToken(string $token, string $ip): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $document = self::lockDocumentByToken($db, $token);
            self::assertFinalizedQuote($document);

            if (($document['statut_devis'] ?? null) === 'accepte') {
                $db->commit();
                $document['workflow_state'] = 'accepte';
                return $document;
            }

            QuotePolicy::fromConfiguration()->assertOpen(
                $document['statut_devis'] ?? null,
                $document['signature_expires_at'] ?? null,
                $document['date_emission'] ?? null,
            );

            $stmt = $db->prepare(
                "UPDATE document_facturation
                 SET signed_at = NOW(), signed_ip = ?, statut_devis = 'accepte', date_decision_devis = NOW(),
                     signature_token_hash = NULL, token_signature = NULL
                 WHERE document_id = ?",
            );
            $stmt->execute([$ip, (int) $document['document_id']]);
            $db->commit();

            $document['statut_devis'] = 'accepte';
            $document['workflow_state'] = 'accepte';
            return $document;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function decide(int $documentId, string $decision): void
    {
        if (!in_array($decision, ['accepte', 'refuse'], true)) {
            throw new RuntimeException('Décision de devis invalide.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $document = self::lockDocumentById($db, $documentId);
            self::assertFinalizedQuote($document);
            $current = $document['statut_devis'] ?? null;

            if ($current === $decision) {
                $db->commit();
                return;
            }
            if ($current !== null && $current !== '') {
                throw new RuntimeException('La décision sur ce devis est déjà définitive.');
            }
            QuotePolicy::fromConfiguration()->assertOpen(
                null,
                $document['signature_expires_at'] ?? null,
                $document['date_emission'] ?? null,
            );

            $stmt = $db->prepare(
                'UPDATE document_facturation
                 SET statut_devis = ?, date_decision_devis = NOW(),
                     signature_token_hash = NULL, token_signature = NULL
                 WHERE document_id = ?',
            );
            $stmt->execute([$decision, $documentId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $document */
    public static function workflowState(array $document): string
    {
        return QuotePolicy::fromConfiguration()->workflowState($document);
    }

    private static function lockDocumentById(PDO $db, int $documentId): array
    {
        $stmt = $db->prepare('SELECT * FROM document_facturation WHERE document_id = ? FOR UPDATE');
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        if (!$document) {
            throw new RuntimeException('Document introuvable.');
        }

        return $document;
    }

    private static function lockDocumentByToken(PDO $db, string $token): array
    {
        $stmt = $db->prepare(
            "SELECT * FROM document_facturation
             WHERE type_document = 'devis' AND statut = 'finalise'
               AND (signature_token_hash = ? OR token_signature = ?)
             LIMIT 1 FOR UPDATE",
        );
        $stmt->execute([hash('sha256', trim($token)), trim($token)]);
        $document = $stmt->fetch();
        if (!$document) {
            throw new RuntimeException('Lien de signature invalide ou expiré.');
        }

        return $document;
    }

    private static function assertFinalizedQuote(array $document): void
    {
        if (($document['type_document'] ?? '') !== 'devis') {
            throw new RuntimeException('Ce document n’est pas un devis.');
        }
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new RuntimeException('Seuls les devis finalisés peuvent recevoir une décision.');
        }
    }
}
