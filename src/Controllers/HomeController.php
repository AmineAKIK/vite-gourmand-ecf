<?php

namespace App\Controllers;

use App\Config\Configuration;
use App\Models\AvisModel;
use App\Models\SiteImageModel;

class HomeController
{
    public function index(): void
    {
        $avisValides = AvisModel::getHomepage();
        $siteImages = SiteImageModel::getAll();
        $heroUrl = isset($siteImages['hero']) && $siteImages['hero'] !== ''
            ? imageUrl($siteImages['hero'], '')
            : null;
        $preloadImages = $heroUrl !== null ? [$heroUrl] : [];
        $heroSousTitre = Configuration::get('content.home.hero_subtitle');
        $heroParagraphe = Configuration::get('content.home.hero_paragraph');

        view('pages/home', compact(
            'avisValides',
            'preloadImages',
            'heroUrl',
            'heroSousTitre',
            'heroParagraphe',
        ));
    }
}
