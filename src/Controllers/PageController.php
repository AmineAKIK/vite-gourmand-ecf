<?php

namespace App\Controllers;

use App\Config\Configuration;

class PageController
{
    public function mentions(): void
    {
        $mentionsContenu = Configuration::get('legal.notices_content') ?? '';
        view('pages/mentions', compact('mentionsContenu'));
    }

    public function cgv(): void
    {
        $cgvContenu = Configuration::get('legal.terms_content') ?? '';
        view('pages/cgv', compact('cgvContenu'));
    }
}
