<?php

namespace App\Controllers;

use App\Models\SiteConfigModel;

class PageController
{
    public function mentions(): void
    {
        $mentionsContenu = SiteConfigModel::get('mentions_contenu') ?? '';
        view('pages/mentions', compact('mentionsContenu'));
    }

    public function cgv(): void
    {
        $cgvContenu = SiteConfigModel::get('cgv_contenu') ?? '';
        view('pages/cgv', compact('cgvContenu'));
    }
}
