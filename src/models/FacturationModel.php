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
                client_siren VARCHAR(20) NULL,
                adresse_livraison VARCHAR(255) NULL,
                ville_livraison VARCHAR(120) NULL,
                code_postal_livraison VARCHAR(20) NULL,
                categorie_operation VARCHAR(30) NOT NULL DEFAULT 'mixte',
                option_tva_debits TINYINT(1) NOT NULL DEFAULT 0,
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
                archive_path VARCHAR(255) NULL,
                sent_at DATETIME NULL,
                sent_by INT NULL,
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
        self::addColumnIfMissing('document_facturation', 'archive_path', 'VARCHAR(255) NULL');
        self::addColumnIfMissing('document_facturation', 'sent_at', 'DATETIME NULL');
        self::addColumnIfMissing('document_facturation', 'sent_by', 'INT NULL');
        self::addColumnIfMissing('document_facturation', 'client_siren', 'VARCHAR(20) NULL');
        self::addColumnIfMissing('document_facturation', 'adresse_livraison', 'VARCHAR(255) NULL');
        self::addColumnIfMissing('document_facturation', 'ville_livraison', 'VARCHAR(120) NULL');
        self::addColumnIfMissing('document_facturation', 'code_postal_livraison', 'VARCHAR(20) NULL');
        self::addColumnIfMissing('document_facturation', 'categorie_operation', "VARCHAR(30) NOT NULL DEFAULT 'mixte'");
        self::addColumnIfMissing('document_facturation', 'option_tva_debits', 'TINYINT(1) NOT NULL DEFAULT 0');
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
                    client_code_postal, client_siren, adresse_livraison, ville_livraison,
                    code_postal_livraison, categorie_operation, option_tva_debits,
                    entreprise_snapshot, note_publique, mention_legale,
                    total_ht, total_tva, total_ttc, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
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
                '',
                $commande['adresse_livraison'] ?? '',
                $commande['ville_livraison'] ?? '',
                $commande['code_postal_livraison'] ?? '',
                'mixte',
                0,
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
                    client_siren = ?, adresse_livraison = ?, ville_livraison = ?, code_postal_livraison = ?,
                    categorie_operation = ?, option_tva_debits = ?,
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
                preg_replace('/\D+/', '', (string)($payload['client_siren'] ?? '')),
                trim((string)($payload['adresse_livraison'] ?? '')),
                trim((string)($payload['ville_livraison'] ?? '')),
                trim((string)($payload['code_postal_livraison'] ?? '')),
                self::validCategorieOperation((string)($payload['categorie_operation'] ?? 'mixte')),
                !empty($payload['option_tva_debits']) ? 1 : 0,
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
            self::archiveDocument($documentId);
            return $numero;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function archiveDocument(int $documentId): string
    {
        self::ensureSchema();
        $document = self::getById($documentId);
        if (!$document) {
            throw new InvalidArgumentException('Document introuvable.');
        }
        if (($document['statut'] ?? '') !== 'finalise') {
            throw new InvalidArgumentException('Seuls les documents finalisés peuvent être archivés.');
        }

        $filename = self::archiveFilename($document);
        $dir = dirname($filename);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le dossier d\'archive.');
        }

        file_put_contents($filename, self::renderDocumentHtml($document, true));
        $relativePath = 'uploads/facturation/' . basename($filename);
        $stmt = Database::getConnection()->prepare("UPDATE document_facturation SET archive_path = ? WHERE document_id = ?");
        $stmt->execute([$relativePath, $documentId]);
        return $relativePath;
    }

    public static function markSent(int $documentId, ?int $sentBy): void
    {
        self::ensureSchema();
        $stmt = Database::getConnection()->prepare("UPDATE document_facturation SET sent_at = NOW(), sent_by = ? WHERE document_id = ?");
        $stmt->execute([$sentBy, $documentId]);
    }

    public static function renderDocumentHtml(array $document, bool $standalone = false): string
    {
        $type = $document['type_document'] ?? 'facture';
        $isTicket = $type === 'ticket';
        $typeLabel = $isTicket ? 'Ticket de caisse' : 'Facture';
        $entreprise = $document['entreprise'] ?? (json_decode($document['entreprise_snapshot'] ?? '{}', true) ?: []);
        $documentRef = $document['numero_document'] ?: ('Brouillon #' . (int)$document['document_id']);
        $lignes = $document['lignes'] ?? self::getLignes((int)$document['document_id']);
        $operationLabel = self::categorieOperationLabel($document['categorie_operation'] ?? 'mixte');

        $rows = '';
        $ticketRows = '';
        foreach ($lignes as $ligne) {
            $designation = htmlspecialchars($ligne['designation'] ?? '', ENT_QUOTES, 'UTF-8');
            $quantite = htmlspecialchars(formatPriceInput($ligne['quantite'] ?? 0), ENT_QUOTES, 'UTF-8');
            $prixUnitaire = htmlspecialchars(formatPrice($ligne['prix_unitaire_ttc'] ?? 0), ENT_QUOTES, 'UTF-8');
            $tva = htmlspecialchars(formatPriceInput($ligne['taux_tva'] ?? 0), ENT_QUOTES, 'UTF-8');
            $totalTtc = htmlspecialchars(formatPrice($ligne['total_ttc'] ?? 0), ENT_QUOTES, 'UTF-8');

            $rows .= '<tr>'
                . '<td data-label="Désignation">' . $designation . '</td>'
                . '<td data-label="Qté" class="num">' . $quantite . '</td>'
                . '<td data-label="PU TTC" class="num">' . $prixUnitaire . '</td>'
                . '<td data-label="TVA" class="num">' . $tva . ' %</td>'
                . '<td data-label="Total TTC" class="num">' . $totalTtc . '</td>'
                . '</tr>';
            $ticketRows .= '<div class="document-ticket-line">'
                . '<div class="document-ticket-line-main"><strong>' . $designation . '</strong><span>' . $totalTtc . '</span></div>'
                . '<div class="document-ticket-line-meta"><span>Qté ' . $quantite . '</span><span>PU ' . $prixUnitaire . '</span><span>TVA ' . $tva . ' %</span></div>'
                . '</div>';
        }

        $linesHtml = $isTicket
            ? '<div class="document-ticket-lines">' . $ticketRows . '</div>'
            : '<div class="document-lines"><table><thead><tr>'
                . '<th>Désignation</th><th class="num">Qté</th><th class="num">PU TTC</th><th class="num">TVA</th><th class="num">Total TTC</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';

        $html = '<article class="document-preview ' . ($isTicket ? 'document-preview-ticket' : '') . '">'
            . '<header class="document-preview-header"><div>'
            . '<p class="document-brand">Vite &amp; Gourmand</p>'
            . '<address>' . htmlspecialchars($entreprise['adresse'] ?? 'Bordeaux', ENT_QUOTES, 'UTF-8') . '<br>'
            . htmlspecialchars($entreprise['email'] ?? MAIL_FROM, ENT_QUOTES, 'UTF-8') . '</address>'
            . '</div><div class="document-meta">'
            . '<h2>' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p>' . htmlspecialchars($documentRef, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>' . htmlspecialchars(formatDateFr($document['date_emission'] ?? null), ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div></header>'
            . '<section class="document-parties"><div><h3>Client</h3><p><strong>'
            . htmlspecialchars($document['client_nom'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong><br>'
            . htmlspecialchars($document['client_adresse'] ?? '', ENT_QUOTES, 'UTF-8') . '<br>'
            . htmlspecialchars(trim(($document['client_code_postal'] ?? '') . ' ' . ($document['client_ville'] ?? '')), ENT_QUOTES, 'UTF-8') . '<br>'
            . htmlspecialchars($document['client_email'] ?? '', ENT_QUOTES, 'UTF-8') . '</p></div>'
            . '<div><h3>Document</h3><p>'
            . htmlspecialchars($documentRef, ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Prestation : ' . htmlspecialchars(formatDateFr($document['date_prestation'] ?? null), ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Opération : ' . htmlspecialchars($operationLabel, ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Statut : finalisé</p></div></section>'
            . '<section class="document-electronic"><h3>Préparation facturation électronique</h3><p>'
            . 'SIREN client : ' . htmlspecialchars($document['client_siren'] ?: 'non renseigné', ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Livraison : ' . htmlspecialchars(trim(($document['adresse_livraison'] ?? '') . ', ' . ($document['code_postal_livraison'] ?? '') . ' ' . ($document['ville_livraison'] ?? '')), ENT_QUOTES, 'UTF-8') . '<br>'
            . (!empty($document['option_tva_debits']) ? 'Option TVA sur les débits : oui' : 'Option TVA sur les débits : non')
            . '</p></section>'
            . $linesHtml
            . '<section class="document-totals"><div>'
            . (!empty($document['note_publique']) ? '<p>' . nl2br(htmlspecialchars($document['note_publique'], ENT_QUOTES, 'UTF-8')) . '</p>' : '')
            . '</div><dl>'
            . '<div><dt>Total HT</dt><dd>' . htmlspecialchars(formatPrice($document['total_ht'] ?? 0), ENT_QUOTES, 'UTF-8') . '</dd></div>'
            . '<div><dt>TVA</dt><dd>' . htmlspecialchars(formatPrice($document['total_tva'] ?? 0), ENT_QUOTES, 'UTF-8') . '</dd></div>'
            . '<div class="document-total-main"><dt>Total TTC</dt><dd>' . htmlspecialchars(formatPrice($document['total_ttc'] ?? 0), ENT_QUOTES, 'UTF-8') . '</dd></div>'
            . '</dl></section>'
            . (!empty($document['mention_legale']) ? '<footer class="document-footer">' . nl2br(htmlspecialchars($document['mention_legale'], ENT_QUOTES, 'UTF-8')) . '</footer>' : '')
            . '</article>';

        if (!$standalone) {
            return $html;
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($documentRef, ENT_QUOTES, 'UTF-8')
            . '</title><style>'
            . self::archiveCss()
            . '</style></head><body>' . $html . '</body></html>';
    }

    public static function eInvoicingPayload(int $documentId): array
    {
        self::ensureSchema();
        $document = self::getById($documentId);
        if (!$document) {
            throw new InvalidArgumentException('Document introuvable.');
        }

        $warnings = [];
        if (($document['type_document'] ?? '') === 'facture' && empty($document['client_siren'])) {
            $warnings[] = 'SIREN client non renseigné. Obligatoire pour une facture B2B concernée par la facturation électronique.';
        }
        if (empty($document['categorie_operation'])) {
            $warnings[] = 'Catégorie d’opération non renseignée.';
        }
        if (empty($document['adresse_livraison'])) {
            $warnings[] = 'Adresse de livraison non renseignée.';
        }

        return [
            'format' => 'vite_gourmand_e_invoice_preparation_v1',
            'ready_for_platform_mapping' => empty($warnings),
            'warnings' => $warnings,
            'document' => [
                'id' => (int)$document['document_id'],
                'type' => $document['type_document'],
                'statut' => $document['statut'],
                'numero' => $document['numero_document'],
                'date_emission' => $document['date_emission'],
                'date_prestation' => $document['date_prestation'],
                'categorie_operation' => $document['categorie_operation'],
                'categorie_operation_label' => self::categorieOperationLabel($document['categorie_operation'] ?? 'mixte'),
                'option_tva_debits' => (bool)($document['option_tva_debits'] ?? false),
            ],
            'vendeur' => $document['entreprise'],
            'client' => [
                'nom' => $document['client_nom'],
                'email' => $document['client_email'],
                'telephone' => $document['client_telephone'],
                'adresse_facturation' => [
                    'adresse' => $document['client_adresse'],
                    'code_postal' => $document['client_code_postal'],
                    'ville' => $document['client_ville'],
                    'pays' => 'FR',
                ],
                'siren' => $document['client_siren'],
            ],
            'livraison' => [
                'adresse' => $document['adresse_livraison'],
                'code_postal' => $document['code_postal_livraison'],
                'ville' => $document['ville_livraison'],
                'pays' => 'FR',
            ],
            'lignes' => array_map(static fn($ligne) => [
                'designation' => $ligne['designation'],
                'quantite' => (float)$ligne['quantite'],
                'prix_unitaire_ht' => (float)$ligne['prix_unitaire_ht'],
                'prix_unitaire_ttc' => (float)$ligne['prix_unitaire_ttc'],
                'taux_tva' => (float)$ligne['taux_tva'],
                'total_ht' => (float)$ligne['total_ht'],
                'total_tva' => (float)$ligne['total_tva'],
                'total_ttc' => (float)$ligne['total_ttc'],
            ], $document['lignes'] ?? []),
            'totaux' => [
                'total_ht' => (float)$document['total_ht'],
                'total_tva' => (float)$document['total_tva'],
                'total_ttc' => (float)$document['total_ttc'],
                'devise' => 'EUR',
            ],
        ];
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

    private static function archiveFilename(array $document): string
    {
        $ref = $document['numero_document'] ?: ('DOC-' . (int)$document['document_id']);
        $safeRef = preg_replace('/[^A-Z0-9_-]+/i', '-', $ref) ?: ('document-' . (int)$document['document_id']);
        return dirname(__DIR__, 2) . '/public/uploads/facturation/' . $safeRef . '.html';
    }

    private static function archiveCss(): string
    {
        return "
            body{margin:0;padding:32px;background:#f7f3ec;color:#111827;font-family:Arial,sans-serif}
            .document-preview{width:min(100%,920px);max-width:920px;margin:0 auto;background:#fff;padding:32px;border:1px solid #ddd;box-sizing:border-box}
            .document-preview-ticket{width:min(100%,430px);max-width:430px}
            .document-preview-header,.document-parties,.document-totals{display:grid;grid-template-columns:1fr 1fr;gap:20px}
            .document-preview-header{border-bottom:2px solid #8B1A2B;padding-bottom:16px}
            .document-brand{margin:0 0 8px;color:#8B1A2B;font-size:24px;font-weight:700}
            address,p{margin:0;line-height:1.45}.document-meta{text-align:right}.document-meta h2{margin:0 0 8px;font-size:28px}
            .document-parties{margin:24px 0}.document-parties h3{margin:0 0 8px;color:#5F6470;font-size:12px;text-transform:uppercase}
            .document-electronic{margin:24px 0;padding:14px 16px;border:1px solid #ead0d4;background:#FDF6EC}.document-electronic h3{margin:0 0 8px;color:#5F6470;font-size:12px;text-transform:uppercase}.document-electronic p{margin:0;color:#4B5563;overflow-wrap:anywhere}
            table{width:100%;border-collapse:collapse}th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb}th{color:#5F6470;font-size:12px;text-align:left}
            .document-ticket-lines{display:grid;border-top:1px solid #e5e7eb}.document-ticket-line{display:grid;gap:6px;padding:12px 0;border-bottom:1px solid #e5e7eb}
            .document-ticket-line-main{display:flex;align-items:start;justify-content:space-between;gap:14px}.document-ticket-line-main strong{min-width:0}.document-ticket-line-main span{flex:0 0 auto;font-weight:700;white-space:nowrap}
            .document-ticket-line-meta{display:flex;flex-wrap:wrap;gap:6px 12px;color:#5F6470;font-size:12px}
            .num{text-align:right}.document-totals{margin-top:24px}.document-totals dl{margin:0;justify-self:end;min-width:280px}
            .document-totals dl div{display:flex;justify-content:space-between;gap:24px;padding:6px 0}
            .document-total-main{border-top:2px solid #8B1A2B;color:#8B1A2B;font-weight:700;font-size:18px}
            .document-footer{margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;color:#4B5563;font-size:13px}
            @media(max-width:700px){body{padding:12px}.document-preview{padding:16px}.document-preview-header,.document-parties,.document-totals{grid-template-columns:1fr}.document-meta{text-align:left}.document-totals dl{justify-self:stretch;min-width:0}thead{display:none}table,tbody,tr,td,th{display:block;width:100%}table{min-width:0;border-collapse:separate;border-spacing:0}tbody{display:grid;gap:12px}tr{box-sizing:border-box;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fdf6ec}td{box-sizing:border-box;display:flex;align-items:baseline;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid #e5e7eb;text-align:right!important}td:before{content:attr(data-label);flex:0 0 auto;color:#5F6470;font-size:12px;font-weight:700;text-align:left}td:first-child{display:block;padding-top:0;font-weight:700;text-align:left!important}td:first-child:before{content:none}td:last-child{padding-bottom:0;border-bottom:0;color:#8B1A2B;font-weight:700}.document-ticket-line-meta{font-size:12px}}
        ";
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

    private static function validCategorieOperation(string $value): string
    {
        return in_array($value, ['biens', 'services', 'mixte'], true) ? $value : 'mixte';
    }

    private static function categorieOperationLabel(string $value): string
    {
        return match (self::validCategorieOperation($value)) {
            'biens' => 'Livraison de biens',
            'services' => 'Prestation de services',
            default => 'Livraison de biens et prestation de services',
        };
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
