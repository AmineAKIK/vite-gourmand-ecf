<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\BillingDocumentPolicy;
use PDO;
use RuntimeException;
use Throwable;

final class BillingDraftService
{
    public static function update(int $documentId, array $payload): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT statut FROM document_facturation WHERE document_id = ? FOR UPDATE');
            $stmt->execute([$documentId]);
            $status = $stmt->fetchColumn();
            if ($status === false) {
                throw new RuntimeException('Document introuvable.');
            }
            BillingDocumentPolicy::assertDraft((string) $status);

            $lines = self::linesFromPayload($payload);
            if ($lines === []) {
                throw new RuntimeException('Le document doit contenir au moins une ligne.');
            }
            $totals = self::totalsFromLines($lines);

            $rawAcompte = trim((string) ($payload['montant_acompte_verse'] ?? ''));
            $montantAcompte = ($rawAcompte !== '' && is_numeric($rawAcompte) && (float) $rawAcompte >= 0)
                ? round((float) $rawAcompte, 2)
                : null;

            $update = $db->prepare(
                "UPDATE document_facturation
                 SET date_emission = ?, date_prestation = ?, client_nom = ?, client_email = ?,
                     client_telephone = ?, client_adresse = ?, client_ville = ?, client_code_postal = ?,
                     client_siren = ?, adresse_livraison = ?, ville_livraison = ?, code_postal_livraison = ?,
                     categorie_operation = ?, option_tva_debits = ?, note_publique = ?, mention_legale = ?,
                     montant_acompte_verse = ?, total_ht = ?, total_tva = ?, total_ttc = ?
                 WHERE document_id = ? AND statut = 'brouillon'",
            );
            $update->execute([
                self::dateOrToday((string) ($payload['date_emission'] ?? '')),
                self::dateOrNull((string) ($payload['date_prestation'] ?? '')),
                trim((string) ($payload['client_nom'] ?? '')),
                trim((string) ($payload['client_email'] ?? '')),
                trim((string) ($payload['client_telephone'] ?? '')),
                trim((string) ($payload['client_adresse'] ?? '')),
                trim((string) ($payload['client_ville'] ?? '')),
                trim((string) ($payload['client_code_postal'] ?? '')),
                preg_replace('/\D+/', '', (string) ($payload['client_siren'] ?? '')),
                trim((string) ($payload['adresse_livraison'] ?? '')),
                trim((string) ($payload['ville_livraison'] ?? '')),
                trim((string) ($payload['code_postal_livraison'] ?? '')),
                self::category((string) ($payload['categorie_operation'] ?? 'mixte')),
                !empty($payload['option_tva_debits']) ? 1 : 0,
                trim((string) ($payload['note_publique'] ?? '')),
                trim((string) ($payload['mention_legale'] ?? '')),
                $montantAcompte,
                $totals['ht'],
                $totals['tva'],
                $totals['ttc'],
                $documentId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Le brouillon a changé pendant la modification.');
            }

            self::replaceLines($db, $documentId, $lines);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function replaceLines(PDO $db, int $documentId, array $lines): void
    {
        $db->prepare('DELETE FROM document_facturation_ligne WHERE document_id = ?')->execute([$documentId]);
        $stmt = $db->prepare(
            'INSERT INTO document_facturation_ligne (
                document_id, designation, quantite, prix_unitaire_ht, prix_unitaire_ttc,
                taux_tva, total_ht, total_tva, total_ttc, ordre
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($lines as $line) {
            $stmt->execute([
                $documentId,
                $line['designation'],
                $line['quantite'],
                $line['prix_unitaire_ht'],
                $line['prix_unitaire_ttc'],
                $line['taux_tva'],
                $line['total_ht'],
                $line['total_tva'],
                $line['total_ttc'],
                $line['ordre'],
            ]);
        }
    }

    private static function linesFromPayload(array $payload): array
    {
        $designations = is_array($payload['designation'] ?? null) ? $payload['designation'] : [];
        $quantities = is_array($payload['quantite'] ?? null) ? $payload['quantite'] : [];
        $prices = is_array($payload['prix_unitaire_ttc'] ?? null) ? $payload['prix_unitaire_ttc'] : [];
        $taxRates = is_array($payload['taux_tva'] ?? null) ? $payload['taux_tva'] : [];
        $lines = [];

        foreach ($designations as $index => $designation) {
            $designation = trim((string) $designation);
            if ($designation === '') {
                continue;
            }
            $quantity = max(0.01, (float) str_replace(',', '.', (string) ($quantities[$index] ?? 1)));
            $priceTtc = (float) str_replace(',', '.', (string) ($prices[$index] ?? 0));
            $taxRate = max(0, (float) str_replace(',', '.', (string) ($taxRates[$index] ?? 10)));
            $unitHt = $taxRate > 0 ? $priceTtc / (1 + ($taxRate / 100)) : $priceTtc;
            $totalTtc = $quantity * $priceTtc;
            $totalHt = $quantity * $unitHt;

            $lines[] = [
                'designation' => $designation,
                'quantite' => round($quantity, 2),
                'prix_unitaire_ht' => round($unitHt, 2),
                'prix_unitaire_ttc' => round($priceTtc, 2),
                'taux_tva' => round($taxRate, 2),
                'total_ht' => round($totalHt, 2),
                'total_tva' => round($totalTtc - $totalHt, 2),
                'total_ttc' => round($totalTtc, 2),
                'ordre' => count($lines) + 1,
            ];
        }

        return $lines;
    }

    private static function totalsFromLines(array $lines): array
    {
        $ht = $tva = $ttc = 0.0;
        foreach ($lines as $line) {
            $ht += (float) $line['total_ht'];
            $tva += (float) $line['total_tva'];
            $ttc += (float) $line['total_ttc'];
        }
        return ['ht' => round($ht, 2), 'tva' => round($tva, 2), 'ttc' => round($ttc, 2)];
    }

    private static function category(string $value): string
    {
        return in_array($value, ['biens', 'services', 'mixte'], true) ? $value : 'mixte';
    }

    private static function dateOrToday(string $value): string
    {
        return self::dateOrNull($value) ?? date('Y-m-d');
    }

    private static function dateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof \DateTimeImmutable ? $date->format('Y-m-d') : null;
    }
}
