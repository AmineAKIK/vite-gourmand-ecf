<?php
// src/models/FacturationModel.php

class FacturationModel
{
    private const DEFAULT_TVA = 10.0;
    private const TYPES = ['facture', 'ticket'];

    public static function ensureSchema(): void
    {
        $db = Database::getConnection();
        $db->exec("
            CREATE TABLE IF NOT EXISTS document_facturation (
                document_id INT AUTO_INCREMENT PRIMARY KEY,
                commande_id INT NOT NULL,
                type_document VARCHAR(20) NOT NULL,
                statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
                numero_document VARCHAR(50),
                date_emission DATE NOT NULL,
                date_prestation DATE,
                client_nom VARCHAR(160) NOT NULL DEFAULT '',
                client_email VARCHAR(190) NOT NULL DEFAULT '',
                client_telephone VARCHAR(40) NOT NULL DEFAULT '',
                client_adresse VARCHAR(255) NOT NULL DEFAULT '',
                client_ville VARCHAR(120) NOT NULL DEFAULT '',
                client_code_postal VARCHAR(20) NOT NULL DEFAULT '',
                entreprise_snapshot LONGTEXT,
                note_publique TEXT,
                mention_legale TEXT,
                total_ht DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_tva DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_ttc DECIMAL(10,2) NOT NULL DEFAULT 0,
                created_by INT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                finalized_at DATETIME NULL,
                finalized_by INT NULL,
                INDEX idx_document_facturation_commande (commande_id),
                INDEX idx_document_facturation_type (type_document),
                INDEX idx_document_facturation_statut (statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS document_facturation_ligne (
                ligne_document_id INT AUTO_INCREMENT PRIMARY KEY,
                document_id INT NOT NULL,
                designation VARCHAR(255) NOT NULL,
                quantite DECIMAL(10,2) NOT NULL DEFAULT 1,
                prix_unitaire_ht DECIMAL(10,2) NOT NULL DEFAULT 0,
                prix_unitaire_ttc DECIMAL(10,2) NOT NULL DEFAULT 0,
                taux_tva DECIMAL(5,2) NOT NULL DEFAULT 10,
                total_ht DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_tva DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_ttc DECIMAL(10,2) NOT NULL DEFAULT 0,
                ordre INT NOT NULL DEFAULT 0,
                INDEX idx_document_facturation_ligne_document (document_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS document_sequence (
                type_document VARCHAR(20) NOT NULL,
                annee INT NOT NULL,
                dernier_numero INT NOT NULL DEFAULT 0,
                PRIMARY KEY (type_document, annee)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        self::addColumnIfMissing('document_facturation', 'finalized_at', 'DATETIME NULL');
        self::addColumnIfMissing('document_facturation', 'finalized_by', 'INT NULL');
    }

    public static function listByCommandeIds(array $commandeIds): array
    {
        self::ensureSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', $commandeIds))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare("
            SELECT *
            FROM document_facturation
            WHERE commande_id IN ($placeholders)
            ORDER BY updated_at DESC, document_id DESC
        ");
        $stmt->execute($ids);

        $documents = [];
        foreach ($stmt->fetchAll() as $document) {
            $documents[(int)$document['commande_id']][] = $document;
        }
        return $documents;
    }

    public static function getById(int $documentId): ?array
    {
        self::ensureSchema();
        $stmt = Database::getConnection()->prepare("SELECT * FROM document_facturation WHERE document_id = ?");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        if (!$document) {
            return null;
        }

        $document['lignes'] = self::getLignes($documentId);
        $document['entreprise'] = json_decode($document['entreprise_snapshot'] ?? '{}', true) ?: [];
        return $document;
    }

    public static function getLignes(int $documentId): array
    {
        self::ensureSchema();
        $stmt = Database::getConnection()->prepare("
            SELECT *
            FROM document_facturation_ligne
            WHERE document_id = ?
            ORDER BY ordre ASC, ligne_document_id ASC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    public static function createDraftFromCommande(int $commandeId, string $type, ?int $createdBy): int
    {
        self::ensureSchema();
        self::assertType($type);

        $existing = self::findDraftForCommande($commandeId, $type);
        if ($existing) {
            return (int)$existing['document_id'];
        }
        $finalized = self::findFinalizedForCommande($commandeId, $type);
        if ($finalized) {
            return (int)$finalized['document_id'];
        }

        $commande = CommandeModel::getById($commandeId);
        if (!$commande) {
            throw new InvalidArgumentException('Commande introuvable.');
        }

        $lignesCommande = CommandeModel::getLignes($commandeId);
        if (!$lignesCommande) {
            throw new InvalidArgumentException('Impossible de créer un document sans lignes de commande.');
        }

        $clientNom = personFullName($commande);
        $clientAdresse = trim(($commande['adresse_livraison'] ?? ''));
        $totals = ['ht' => 0.0, 'tva' => 0.0, 'ttc' => 0.0];
        $lignes = [];

        foreach ($lignesCommande as $index => $ligne) {
            $nbPersonnes = (int)($ligne['nombre_personne'] ?? 1);
            $designation = ($ligne['menu_titre'] ?? 'Menu') . ' - ' . $nbPersonnes . ' pers.';
            $menuNetTtc = (float)($ligne['prix_menu'] ?? 0);
            $menuBrutTtc = !empty($ligne['prix_par_personne'])
                ? round((float)$ligne['prix_par_personne'] * $nbPersonnes, 2)
                : $menuNetTtc;
            $ttc = $menuBrutTtc;
            $computed = self::lineTotals(1, $ttc, self::DEFAULT_TVA);
            $lignes[] = [
                'designation' => $designation,
                'quantite' => 1,
                'prix_unitaire_ttc' => $ttc,
                'prix_unitaire_ht' => $computed['unit_ht'],
                'taux_tva' => self::DEFAULT_TVA,
                'total_ht' => $computed['total_ht'],
                'total_tva' => $computed['total_tva'],
                'total_ttc' => $computed['total_ttc'],
                'ordre' => count($lignes) + 1,
            ];
            $totals['ht'] += $computed['total_ht'];
            $totals['tva'] += $computed['total_tva'];
            $totals['ttc'] += $computed['total_ttc'];

            $remiseTtc = round($menuBrutTtc - $menuNetTtc, 2);
            if ($remiseTtc > 0.01) {
                $remiseLabel = reductionTauxPourcentage() > 0
                    ? 'Réduction volume (' . formatPriceInput(reductionTauxPourcentage()) . ' %)'
                    : 'Réduction volume';
                $remiseComputed = self::lineTotals(1, -$remiseTtc, self::DEFAULT_TVA);
                $lignes[] = [
                    'designation' => $remiseLabel . ' - ' . ($ligne['menu_titre'] ?? 'Menu'),
                    'quantite' => 1,
                    'prix_unitaire_ttc' => -$remiseTtc,
                    'prix_unitaire_ht' => $remiseComputed['unit_ht'],
                    'taux_tva' => self::DEFAULT_TVA,
                    'total_ht' => $remiseComputed['total_ht'],
                    'total_tva' => $remiseComputed['total_tva'],
                    'total_ttc' => $remiseComputed['total_ttc'],
                    'ordre' => count($lignes) + 1,
                ];
                $totals['ht'] += $remiseComputed['total_ht'];
                $totals['tva'] += $remiseComputed['total_tva'];
                $totals['ttc'] += $remiseComputed['total_ttc'];
            }

            $livraisonTtc = (float)($ligne['prix_livraison'] ?? 0);
            if ($livraisonTtc > 0) {
                $livraisonComputed = self::lineTotals(1, $livraisonTtc, self::DEFAULT_TVA);
                $lignes[] = [
                    'designation' => 'Livraison - ' . ($commande['ville_livraison'] ?? 'adresse client'),
                    'quantite' => 1,
                    'prix_unitaire_ttc' => $livraisonTtc,
                    'prix_unitaire_ht' => $livraisonComputed['unit_ht'],
                    'taux_tva' => self::DEFAULT_TVA,
                    'total_ht' => $livraisonComputed['total_ht'],
                    'total_tva' => $livraisonComputed['total_tva'],
                    'total_ttc' => $livraisonComputed['total_ttc'],
                    'ordre' => count($lignes) + 1,
                ];
                $totals['ht'] += $livraisonComputed['total_ht'];
                $totals['tva'] += $livraisonComputed['total_tva'];
                $totals['ttc'] += $livraisonComputed['total_ttc'];
            }
        }

        $ecartCommande = round((float)($commande['prix_total'] ?? 0) - $totals['ttc'], 2);
        if (abs($ecartCommande) > 0.01) {
            $adjustment = self::lineTotals(1, $ecartCommande, self::DEFAULT_TVA);
            $lignes[] = [
                'designation' => 'Ajustement tarification commande',
                'quantite' => 1,
                'prix_unitaire_ttc' => $ecartCommande,
                'prix_unitaire_ht' => $adjustment['unit_ht'],
                'taux_tva' => self::DEFAULT_TVA,
                'total_ht' => $adjustment['total_ht'],
                'total_tva' => $adjustment['total_tva'],
                'total_ttc' => $adjustment['total_ttc'],
                'ordre' => count($lignes) + 1,
            ];
            $totals['ht'] += $adjustment['total_ht'];
            $totals['tva'] += $adjustment['total_tva'];
            $totals['ttc'] += $adjustment['total_ttc'];
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO document_facturation (
                    commande_id, type_document, statut, date_emission, date_prestation,
                    client_nom, client_email, client_telephone, client_adresse, client_ville,
                    client_code_postal, entreprise_snapshot, note_publique, mention_legale,
                    total_ht, total_tva, total_ttc, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $commandeId,
                $type,
                'brouillon',
                date('Y-m-d'),
                $commande['date_prestation'] ?? null,
                $clientNom,
                $commande['email'] ?? '',
                $commande['telephone'] ?? '',
                $clientAdresse,
                $commande['ville_livraison'] ?? '',
                $commande['code_postal_livraison'] ?? '',
                json_encode(self::entrepriseSnapshot(), JSON_UNESCAPED_UNICODE),
                self::defaultNote($type),
                self::defaultMention($type),
                round($totals['ht'], 2),
                round($totals['tva'], 2),
                round($totals['ttc'], 2),
                $createdBy,
            ]);

            $documentId = (int)$db->lastInsertId();
            self::replaceLignes($documentId, $lignes);
            $db->commit();
            return $documentId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function updateDraft(int $documentId, array $payload): void
    {
        self::ensureSchema();
        $document = self::getById($documentId);
        if (!$document) {
            throw new InvalidArgumentException('Document introuvable.');
        }
        if (($document['statut'] ?? '') !== 'brouillon') {
            throw new InvalidArgumentException('Seuls les brouillons peuvent être modifiés.');
        }

        $lignes = self::linesFromPayload($payload);
        if (!$lignes) {
            throw new InvalidArgumentException('Le document doit contenir au moins une ligne.');
        }

        $totals = self::totalsFromLines($lignes);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                UPDATE document_facturation
                SET date_emission = ?, date_prestation = ?, client_nom = ?, client_email = ?,
                    client_telephone = ?, client_adresse = ?, client_ville = ?, client_code_postal = ?,
                    note_publique = ?, mention_legale = ?, total_ht = ?, total_tva = ?, total_ttc = ?
                WHERE document_id = ?
            ");
            $stmt->execute([
                self::dateOrToday($payload['date_emission'] ?? ''),
                self::dateOrNull($payload['date_prestation'] ?? ''),
                trim((string)($payload['client_nom'] ?? '')),
                trim((string)($payload['client_email'] ?? '')),
                trim((string)($payload['client_telephone'] ?? '')),
                trim((string)($payload['client_adresse'] ?? '')),
                trim((string)($payload['client_ville'] ?? '')),
                trim((string)($payload['client_code_postal'] ?? '')),
                trim((string)($payload['note_publique'] ?? '')),
                trim((string)($payload['mention_legale'] ?? '')),
                $totals['ht'],
                $totals['tva'],
                $totals['ttc'],
                $documentId,
            ]);
            self::replaceLignes($documentId, $lignes);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function finalizeDraft(int $documentId, ?int $finalizedBy): string
    {
        self::ensureSchema();
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM document_facturation WHERE document_id = ? FOR UPDATE");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch();
            if (!$document) {
                throw new InvalidArgumentException('Document introuvable.');
            }
            if (($document['statut'] ?? '') !== 'brouillon') {
                throw new InvalidArgumentException('Ce document est déjà finalisé.');
            }

            $lignesStmt = $db->prepare("SELECT ligne_document_id FROM document_facturation_ligne WHERE document_id = ? LIMIT 1");
            $lignesStmt->execute([$documentId]);
            $lignes = $lignesStmt->fetch();
            if (!$lignes) {
                throw new InvalidArgumentException('Impossible de finaliser un document sans lignes.');
            }
            if (trim((string)($document['client_nom'] ?? '')) === '') {
                throw new InvalidArgumentException('Le nom du client est obligatoire avant finalisation.');
            }

            $numero = self::nextNumeroDocument($db, $document['type_document'], $document['date_emission'] ?? date('Y-m-d'));
            $update = $db->prepare("
                UPDATE document_facturation
                SET statut = 'finalise', numero_document = ?, finalized_at = NOW(), finalized_by = ?
                WHERE document_id = ?
            ");
            $update->execute([$numero, $finalizedBy, $documentId]);
            $db->commit();
            return $numero;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function findDraftForCommande(int $commandeId, string $type): ?array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT *
            FROM document_facturation
            WHERE commande_id = ? AND type_document = ? AND statut = 'brouillon'
            ORDER BY document_id DESC
            LIMIT 1
        ");
        $stmt->execute([$commandeId, $type]);
        return $stmt->fetch() ?: null;
    }

    private static function findFinalizedForCommande(int $commandeId, string $type): ?array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT *
            FROM document_facturation
            WHERE commande_id = ? AND type_document = ? AND statut = 'finalise'
            ORDER BY finalized_at DESC, document_id DESC
            LIMIT 1
        ");
        $stmt->execute([$commandeId, $type]);
        return $stmt->fetch() ?: null;
    }

    private static function nextNumeroDocument(PDO $db, string $type, string $dateEmission): string
    {
        self::assertType($type);
        $timestamp = strtotime($dateEmission) ?: time();
        $annee = (int)date('Y', $timestamp);
        $prefix = $type === 'ticket' ? 'TCK' : 'FAC';

        $stmt = $db->prepare("
            SELECT dernier_numero
            FROM document_sequence
            WHERE type_document = ? AND annee = ?
            FOR UPDATE
        ");
        $stmt->execute([$type, $annee]);
        $current = $stmt->fetchColumn();
        $next = $current === false ? 1 : ((int)$current + 1);

        if ($current === false) {
            $insert = $db->prepare("INSERT INTO document_sequence (type_document, annee, dernier_numero) VALUES (?,?,?)");
            $insert->execute([$type, $annee, $next]);
        } else {
            $update = $db->prepare("UPDATE document_sequence SET dernier_numero = ? WHERE type_document = ? AND annee = ?");
            $update->execute([$next, $type, $annee]);
        }

        return sprintf('%s-%d-%04d', $prefix, $annee, $next);
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    private static function replaceLignes(int $documentId, array $lignes): void
    {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM document_facturation_ligne WHERE document_id = ?")->execute([$documentId]);
        $stmt = $db->prepare("
            INSERT INTO document_facturation_ligne (
                document_id, designation, quantite, prix_unitaire_ht, prix_unitaire_ttc,
                taux_tva, total_ht, total_tva, total_ttc, ordre
            ) VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        foreach ($lignes as $index => $ligne) {
            $stmt->execute([
                $documentId,
                $ligne['designation'],
                $ligne['quantite'],
                $ligne['prix_unitaire_ht'],
                $ligne['prix_unitaire_ttc'],
                $ligne['taux_tva'],
                $ligne['total_ht'],
                $ligne['total_tva'],
                $ligne['total_ttc'],
                $ligne['ordre'] ?? ($index + 1),
            ]);
        }
    }

    private static function linesFromPayload(array $payload): array
    {
        $designations = $payload['designation'] ?? [];
        $quantites = $payload['quantite'] ?? [];
        $prixUnitaires = $payload['prix_unitaire_ttc'] ?? [];
        $tauxTva = $payload['taux_tva'] ?? [];
        $lignes = [];

        foreach ($designations as $index => $designation) {
            $designation = trim((string)$designation);
            if ($designation === '') {
                continue;
            }

            $quantite = max(0.01, (float)str_replace(',', '.', (string)($quantites[$index] ?? 1)));
            $prixTtc = (float)str_replace(',', '.', (string)($prixUnitaires[$index] ?? 0));
            $tva = max(0, (float)str_replace(',', '.', (string)($tauxTva[$index] ?? self::DEFAULT_TVA)));
            $computed = self::lineTotals($quantite, $prixTtc, $tva);

            $lignes[] = [
                'designation' => $designation,
                'quantite' => round($quantite, 2),
                'prix_unitaire_ht' => $computed['unit_ht'],
                'prix_unitaire_ttc' => round($prixTtc, 2),
                'taux_tva' => round($tva, 2),
                'total_ht' => $computed['total_ht'],
                'total_tva' => $computed['total_tva'],
                'total_ttc' => $computed['total_ttc'],
                'ordre' => count($lignes) + 1,
            ];
        }

        return $lignes;
    }

    private static function lineTotals(float $quantite, float $prixTtc, float $tauxTva): array
    {
        $unitHt = $tauxTva > 0 ? $prixTtc / (1 + ($tauxTva / 100)) : $prixTtc;
        $totalTtc = $quantite * $prixTtc;
        $totalHt = $quantite * $unitHt;
        return [
            'unit_ht' => round($unitHt, 2),
            'total_ht' => round($totalHt, 2),
            'total_tva' => round($totalTtc - $totalHt, 2),
            'total_ttc' => round($totalTtc, 2),
        ];
    }

    private static function totalsFromLines(array $lignes): array
    {
        $totals = ['ht' => 0.0, 'tva' => 0.0, 'ttc' => 0.0];
        foreach ($lignes as $ligne) {
            $totals['ht'] += (float)$ligne['total_ht'];
            $totals['tva'] += (float)$ligne['total_tva'];
            $totals['ttc'] += (float)$ligne['total_ttc'];
        }
        return [
            'ht' => round($totals['ht'], 2),
            'tva' => round($totals['tva'], 2),
            'ttc' => round($totals['ttc'], 2),
        ];
    }

    private static function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Type de document invalide.');
        }
    }

    private static function entrepriseSnapshot(): array
    {
        return [
            'nom' => 'Vite & Gourmand',
            'adresse' => 'Bordeaux',
            'email' => MAIL_FROM,
            'telephone' => '',
            'siret' => '',
            'tva_intracom' => '',
        ];
    }

    private static function defaultNote(string $type): string
    {
        return $type === 'ticket'
            ? 'Merci pour votre commande.'
            : 'Merci de votre confiance.';
    }

    private static function defaultMention(string $type): string
    {
        return $type === 'ticket'
            ? 'Ticket de caisse brouillon - document non finalisé.'
            : 'Facture brouillon - document non finalisé.';
    }

    private static function dateOrToday(string $date): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    }

    private static function dateOrNull(string $date): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }
}
