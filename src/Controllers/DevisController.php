<?php

namespace App\Controllers;

use App\Services\QuoteDecisionService;

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

        $document = QuoteDecisionService::findByToken($token);

        if (!$document) {
            http_response_code(404);
            view('pages/404');
            return;
        }

        $alreadySigned = ($document['statut_devis'] ?? null) === 'accepte';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySigned) {
            verifyCsrf();
            try {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                QuoteDecisionService::acceptWithToken($token, (string)$ip);
                $document = QuoteDecisionService::findByToken($token) ?: $document;
                $alreadySigned = true;
                flash('success', 'Votre devis a bien été accepté. Nous vous contacterons prochainement.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
        }

        view('pages/devis/accepter', compact('document', 'alreadySigned', 'token'));
    }
}
