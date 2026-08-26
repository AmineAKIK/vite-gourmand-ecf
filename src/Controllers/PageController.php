<?php

namespace App\Controllers;

use App\Config\Configuration;
use App\Services\TermsAndConditionsService;
use Throwable;

class PageController
{
    public function mentions(): void
    {
        $mentionsContenu = Configuration::get('legal.notices_content') ?? '';
        view('pages/mentions', compact('mentionsContenu'));
    }

    public function cgv(): void
    {
        $explicitContent = Configuration::get('legal.terms_content') ?? '';
        $termsDocument = null;

        try {
            $termsDocument = TermsAndConditionsService::fromConfiguration()->build();
        } catch (Throwable $exception) {
            error_log('[cgv] génération canonique indisponible : ' . $exception->getMessage());
        }

        view('pages/cgv', compact('termsDocument', 'explicitContent'));
    }
}
