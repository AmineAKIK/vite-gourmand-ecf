<?php

namespace App\Controllers\Workspace;

use App\Models\CommandeModel;
use App\Models\FacturationModel;
use App\Services\MailService;
use App\Services\PricingService;
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
            FacturationModel::updateDraft($documentId, $_POST);
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
                    FacturationModel::updateDraft($documentId, $_POST);
                }
            }
            $numero = FacturationModel::finalizeDraft($documentId, currentUser()['id'] ?? null);
            flash('success', 'Document finalisé : ' . $numero . '.');
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
            FacturationModel::archiveDocument($documentId);
            flash('success', 'Archive du document générée.');
            redirect('/employe/document/apercu?id=' . $documentId);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($documentId ? '/employe/document/apercu?id=' . $documentId : '/employe/commandes');
        }
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

            $relativeArchive = $document['archive_path'] ?: FacturationModel::archiveDocument($documentId);
            $absoluteArchive = dirname(__DIR__, 3) . '/public/' . ltrim($relativeArchive, '/');
            $commande = CommandeModel::getById((int)$document['commande_id']);
            MailService::sendDocumentFacturation($document, $commande ?: [], $absoluteArchive);
            FacturationModel::markSent($documentId, currentUser()['id'] ?? null);

            flash('success', 'Document envoyé au client.');
            redirect('/employe/document/apercu?id=' . $documentId);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($documentId ? '/employe/document/apercu?id=' . $documentId : '/employe/commandes');
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

        $commande  = CommandeModel::getById((int)$document['commande_id']);
        $pageTitle = buildPageTitle('Aperçu ' . $document['type_document']);

        view('pages/employe/document_preview', compact('document', 'commande', 'pageTitle'));
    }
}
