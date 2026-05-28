<?php

namespace App\Controllers;

use App\Models\FacturationModel;

class DevisController
{
    public function accepter(): void
    {
        $token = sanitize($_GET['token'] ?? '');

        if (!$token) {
            http_response_code(404);
            view('pages/404');
            return;
        }

        $document = FacturationModel::getBySignatureToken($token);

        if (!$document || ($document['type_document'] ?? '') !== 'devis' || ($document['statut'] ?? '') !== 'finalise') {
            http_response_code(404);
            view('pages/404');
            return;
        }

        $alreadySigned = $document['signed_at'] !== null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySigned) {
            verifyCsrf();
            try {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                FacturationModel::signDevis($token, (string)$ip);
                $document = FacturationModel::getBySignatureToken($token);
                $alreadySigned = true;
                flash('success', 'Votre devis a bien été accepté. Nous vous contacterons prochainement.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
        }

        view('pages/devis/accepter', compact('document', 'alreadySigned', 'token'));
    }
}
