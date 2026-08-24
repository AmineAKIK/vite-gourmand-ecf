<?php

namespace App\Services;

use App\Config\Database;
use App\Models\FacturationModel;
use InvalidArgumentException;
use RuntimeException;

final class BillingDocumentStorage
{
    private const PRIVATE_PREFIX = 'facturation/';
    private const LEGACY_PREFIX = 'uploads/facturation/';
    private const COLUMNS = ['archive_path', 'pdf_path'];

    public static function ensureArchive(int $documentId): string
    {
        $document = self::document($documentId);
        self::assertFinalized($document);

        $resolved = self::resolveStoredPath($documentId, 'archive_path', $document['archive_path'] ?? null);
        if ($resolved !== null) {
            return $resolved;
        }

        $legacyPath = FacturationModel::archiveDocument($documentId);
        return self::moveGeneratedLegacyFile($documentId, 'archive_path', $legacyPath);
    }

    public static function ensurePdf(int $documentId): string
    {
        $document = self::document($documentId);
        self::assertFinalized($document);

        $resolved = self::resolveStoredPath($documentId, 'pdf_path', $document['pdf_path'] ?? null);
        if ($resolved !== null) {
            return $resolved;
        }

        $legacyPath = FacturationModel::generatePdf($documentId);
        return self::moveGeneratedLegacyFile($documentId, 'pdf_path', $legacyPath);
    }

    /**
     * Déplace à la volée les fichiers historiques encore présents sous public/.
     * Un chemin DB pointant vers un ancien fichier absent reste inchangé : ensureArchive/ensurePdf
     * le régénérera uniquement lorsqu'il est effectivement nécessaire.
     */
    public static function migrateExisting(int $documentId): void
    {
        $document = self::document($documentId);
        foreach (self::COLUMNS as $column) {
            self::resolveStoredPath($documentId, $column, $document[$column] ?? null);
        }
    }

    private static function document(int $documentId): array
    {
        $document = FacturationModel::getById($documentId);
        if (!$document) {
            throw new InvalidArgumentException('Document introuvable.');
        }
        return $document;
    }

    private static function assertFinalized(array $document): void
    {
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new InvalidArgumentException('Seuls les documents finalisés sont accessibles.');
        }
    }

    private static function resolveStoredPath(int $documentId, string $column, ?string $storedPath): ?string
    {
        self::assertColumn($column);
        $storedPath = ltrim(trim((string)$storedPath), '/');
        if ($storedPath === '') {
            return null;
        }

        if (str_starts_with($storedPath, self::PRIVATE_PREFIX)) {
            $privatePath = self::privatePath(basename($storedPath));
            return is_file($privatePath) ? $privatePath : null;
        }

        if (str_starts_with($storedPath, self::LEGACY_PREFIX)) {
            $legacyPath = self::legacyPath(basename($storedPath));
            if (!is_file($legacyPath)) {
                return null;
            }
            return self::moveLegacyFile($documentId, $column, $legacyPath);
        }

        // Ne jamais accepter un chemin arbitraire provenant de la base.
        error_log(sprintf(
            '[facturation] chemin refusé document_id=%d colonne=%s chemin=%s',
            $documentId,
            $column,
            $storedPath
        ));
        return null;
    }

    private static function moveGeneratedLegacyFile(int $documentId, string $column, string $legacyRelativePath): string
    {
        self::assertColumn($column);
        $legacyRelativePath = ltrim($legacyRelativePath, '/');
        if (!str_starts_with($legacyRelativePath, self::LEGACY_PREFIX)) {
            throw new RuntimeException('Chemin de document généré inattendu.');
        }

        $legacyPath = self::legacyPath(basename($legacyRelativePath));
        if (!is_file($legacyPath)) {
            throw new RuntimeException('Document généré introuvable avant sécurisation.');
        }

        return self::moveLegacyFile($documentId, $column, $legacyPath);
    }

    private static function moveLegacyFile(int $documentId, string $column, string $legacyPath): string
    {
        $filename = self::safeFilename(basename($legacyPath));
        self::ensurePrivateDirectory();
        $destination = self::privatePath($filename);

        if (!@rename($legacyPath, $destination)) {
            if (!@copy($legacyPath, $destination)) {
                throw new RuntimeException('Impossible de déplacer le document vers le stockage privé.');
            }
            if (!@unlink($legacyPath)) {
                @unlink($destination);
                throw new RuntimeException('Impossible de supprimer la copie publique du document.');
            }
        }

        @chmod($destination, 0640);
        $relative = self::PRIVATE_PREFIX . $filename;
        self::updateStoredPath($documentId, $column, $relative);
        return $destination;
    }

    private static function updateStoredPath(int $documentId, string $column, string $relativePath): void
    {
        self::assertColumn($column);
        $sql = "UPDATE document_facturation SET {$column} = ? WHERE document_id = ?";
        Database::getConnection()->prepare($sql)->execute([$relativePath, $documentId]);
    }

    private static function ensurePrivateDirectory(): void
    {
        $dir = self::privateRoot();
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le stockage privé de facturation.');
        }
    }

    private static function privateRoot(): string
    {
        return dirname(__DIR__, 2) . '/storage/facturation';
    }

    private static function legacyRoot(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads/facturation';
    }

    private static function privatePath(string $filename): string
    {
        return self::privateRoot() . '/' . self::safeFilename($filename);
    }

    private static function legacyPath(string $filename): string
    {
        return self::legacyRoot() . '/' . self::safeFilename($filename);
    }

    private static function safeFilename(string $filename): string
    {
        if (!preg_match('/\A[A-Za-z0-9_.-]+\.(?:html|pdf)\z/', $filename)) {
            throw new RuntimeException('Nom de fichier de facturation invalide.');
        }
        return $filename;
    }

    private static function assertColumn(string $column): void
    {
        if (!in_array($column, self::COLUMNS, true)) {
            throw new RuntimeException('Colonne de stockage de facturation invalide.');
        }
    }
}
