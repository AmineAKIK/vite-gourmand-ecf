<?php

namespace App\Controllers\Workspace;

use App\Models\CommandeModel;
use App\Models\FacturationModel;
use App\Services\BillingDocumentStorage;
use App\Services\BillingDraftService;
use App\Services\BillingFinalizationService;
use App\Services\MailService;
use App\Services\PricingService;
use App\Services\QuoteDecisionService;
use InvalidArgumentException;
use Throwable;

class DocumentController
{
    public function create(): void
    {
        verifyCsrf();

        $commandeId = (int)($_POST['commande_id']    ?? 0);
        $type       = sanitize($_POST['type_document'] ?? '');

        try {
            $documentId = FacturationModel::createDraftFromCommande($commandeId, $type, currentUser()['id'] ?? null);
            redirect('/employe/document/edit?id=' . $documentId);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/employe/commandes');
        }
    }

    public function edit(): void
    {
        $documentId = (int)($_GET['id'] ?? 0);
        $document   = FacturationModel::getById($documentId);
        if (!$document) {
            flash('error', 'Document introuvable.');
            redirect('/employe/commandes');
        }

        $commande       = CommandeModel::getById((int)$document['commande_id']);
        $tauxTvaOptions = PricingService::tauxTvaActifs();
        $siretMissing   = trim((string)\App\Config\SiteConfig::get('entreprise_siret', '')) === '';
        $pageTitle      = ucfirst($document['type_document']) . ' brouillon — ' . siteName();

        view('pages/employe/document_edit', compact('document', 'commande', 'tauxTvaOptions', 'siretMissing', 'pageTitle'));
    }

    public function update(): void
    {
        verifyCsrf();

        $documentId = (int)($_POST['document_id'] ?? 0);
        try {
            BillingDraftService::update($documentId, $_POST);
            flash('success', 'Brouillon mis à jour.');
            redirect('/employe/document/edit?id=' . $documentId);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($documentId ? '/employe/document/edit?id=' . $documentId : '/employe/commandes');
        }
    }

    public function finalize(): void
    {
        verifyCsrf();

        $documentId = (int)($_POST['document_id'] ?? 0);
        try {
            if (isset($_POST['designation'])) {
                $document = FacturationModel::getById($documentId);
                if ($document && ($document['statut'] ?? '') === 'brouillon') {
                    BillingDraftService::update($documentId, $_POST);
                }
            }

            $result = BillingFinalizationService::finalize($documentId, currentUser()['id'] ?? null);
            $numero = $result['numero'];
            $archiveAbsolute = $result['archive'];
            $document = FacturationModel::getById($documentId);

            if ($document
                && ($document['type_document'] ?? '') === 'devis'
                && !empty($document['client_email'])
                && $archiveAbsolute !== null
            ) {
                try {
                    $commande = CommandeModel::getById((int)$document['commande_id']);
                    MailService::sendDevis($document, $commande ?: [], $archiveAbsolute);
                    BillingDocumentStorage::migrateExisting($documentId);
                    FacturationModel::markSent($documentId, currentUser()['id'] ?? null);
                    flash('success', 'Devis finalisé (' . $numero . ') et envoyé au client.');
                } catch (Throwable $mailErr) {
                    error_log('sendDevis auto : ' . $mailErr->getMessage());
                    flash('success', 'Devis finalisé : ' . $numero . '. (envoi email échoué — envoyez manuellement)');
                }
            } else {
                flash('success', 'Document finalisé : ' . $numero . '.');
            }

            if ($result['archive_warning'] !== null) {
                flash('error', $result['archive_warning']);
            }
            redirect('/employe/document/apercu?id=' . $documentId);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($documentId ? '/employe/document/edit?id=' . $documentId : '/employe/commandes');
        }
    }

    public function archive(): void
    {
        verifyCsrf();

        $documentId = (int)($_POST['document_id'] ?? 0);
        try {
            BillingDocumentStorage::ensureArchive($documentId);
            flash('success', 'Archive du document générée.');
            redirect('/employe/document/apercu?id=' . $documentId);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($documentId ? '/employe/document/apercu?id=' . $documentId : '/employe/commandes');
        }
    }

    public function envoyerSignature(): void
    {
        verifyCsrf();

        $documentId = (int)($_POST['document_id'] ?? 0);
        try {
            $document = FacturationModel::getById($documentId);
            if (!$document) throw new \InvalidArgumentException('Document introuvable.');

            $token = QuoteDecisionService::createSignatureToken($documentId);
            $signatureUrl = rtrim(BASE_URL, '/') . '/devis/accepter?token=' . urlencode($token);
            MailService::sendDevisSignatureRequest($document, $signatureUrl);
            flash('success', 'Email de signature envoyé au client.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect($documentId ? '/employe/document/apercu?id=' . $documentId : '/employe/commandes');
    }

    public function accepterDevis(): void
    {
        verifyCsrf();

        $documentId = (int)($_POST['document_id'] ?? 0);
        try {
            QuoteDecisionService::decide($documentId, 'accepte');
            flash('success', 'Devis marqué comme accepté.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect($documentId ? '/employe/document/apercu?id=' . $documentId : '/employe/commandes');
    }

    public function refuserDevis(): void
    {
        verifyCsrf();

        $documentId = (int)($_POST['document_id'] ?? 0);
        try {
            QuoteDecisionService::decide($documentId, 'refuse');
            flash('success', 'Devis marqué comme refusé.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect($documentId ? '/employe/document/apercu?id=' . $documentId : '/employe/commandes');
    }

    public function send(): void
    {
        verifyCsrf();

        $documentId = (int)($_POST['document_id'] ?? 0);
        try {
            $document = FacturationModel::getById($documentId);
            if (!$document) {
                throw new InvalidArgumentException('Document introuvable.');
            }
            if (($document['statut'] ?? '') !== 'finalise') {
                throw new InvalidArgumentException('Seuls les documents finalisés peuvent être envoyés.');
            }

            $archiveAbsolute = BillingDocumentStorage::ensureArchive($documentId);
            $document = FacturationModel::getById($documentId) ?: $document;
            $commande = CommandeModel::getById((int)$document['commande_id']);
            if (($document['type_document'] ?? '') === 'devis') {
                MailService::sendDevis($document, $commande ?: [], $archiveAbsolute);
            } else {
                MailService::sendDocumentFacturation($document, $commande ?: [], $archiveAbsolute);
            }
            BillingDocumentStorage::migrateExisting($documentId);
            FacturationModel::markSent($documentId, currentUser()['id'] ?? null);

            flash('success', 'Document envoyé au client.');
            redirect('/employe/document/apercu?id=' . $documentId);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($documentId ? '/employe/document/apercu?id=' . $documentId : '/employe/commandes');
        }
    }

    public function exportArchive(): void
    {
        $documentId = (int)($_GET['id'] ?? 0);
        try {
            $document = FacturationModel::getById($documentId);
            if (!$document) {
                throw new InvalidArgumentException('Document introuvable.');
            }
            if (($document['statut'] ?? '') !== 'finalise') {
                throw new InvalidArgumentException('Seuls les documents finalisés peuvent être consultés.');
            }

            $absolutePath = BillingDocumentStorage::ensureArchive($documentId);
            if (!is_file($absolutePath)) {
                throw new \RuntimeException('Archive introuvable après génération.');
            }

            $numero   = $document['numero_document'] ?: ('document-' . $documentId);
            $filename = preg_replace('/[^A-Z0-9_.-]+/i', '-', $numero) . '.html';
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($absolutePath));
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
            readfile($absolutePath);
            exit;
        } catch (Throwable $e) {
            error_log('[facturation] export archive impossible document_id=' . $documentId . ': ' . $e->getMessage());
            http_response_code(404);
            echo 'Document introuvable.';
        }
    }

    public function exportPdf(): void
    {
        $documentId = (int)($_GET['id'] ?? 0);
        try {
            $document = FacturationModel::getById($documentId);
            if (!$document) {
                throw new \InvalidArgumentException('Document introuvable.');
            }
            $absolutePath = BillingDocumentStorage::ensurePdf($documentId);
            if (!is_file($absolutePath)) {
                throw new \RuntimeException('PDF introuvable après génération.');
            }
            $numero   = $document['numero_document'] ?: ('document-' . $documentId);
            $filename = preg_replace('/[^A-Z0-9_.-]+/i', '-', $numero) . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($absolutePath));
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
            readfile($absolutePath);
            exit;
        } catch (Throwable $e) {
            error_log('[facturation] export PDF impossible document_id=' . $documentId . ': ' . $e->getMessage());
            http_response_code(500);
            echo 'Erreur génération PDF.';
        }
    }

    public function export(): void
    {
        $documentId = (int)($_GET['id'] ?? 0);
        try {
            $payload  = FacturationModel::eInvoicingPayload($documentId);
            $filename = ($payload['document']['numero'] ?? ('document-' . $documentId)) . '.json';
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Z0-9_.-]+/i', '-', $filename) . '"');
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function preview(): void
    {
        $documentId = (int)($_GET['id'] ?? 0);
        $document   = FacturationModel::getById($documentId);
        if (!$document) {
            flash('error', 'Document introuvable.');
            redirect('/employe/commandes');
        }

        try {
            BillingDocumentStorage::migrateExisting($documentId);
            $document = FacturationModel::getById($documentId) ?: $document;
        } catch (Throwable $e) {
            error_log('[facturation] migration stockage impossible document_id=' . $documentId . ': ' . $e->getMessage());
        }

        $commande  = CommandeModel::getById((int)$document['commande_id']);
        $pageTitle = buildPageTitle('Aperçu ' . $document['type_document']);

        view('pages/employe/document_preview', compact('document', 'commande', 'pageTitle'));
    }
}
