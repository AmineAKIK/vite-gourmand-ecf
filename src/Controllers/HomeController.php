<?php

namespace App\Controllers;

use App\Models\AvisModel;
use App\Models\SiteConfigModel;
use App\Models\SiteImageModel;

class HomeController {
    public function index(): void {
        $avisValides   = AvisModel::getHomepage();
        $siteImages    = SiteImageModel::getAll();
        $heroUrl        = imageUrl($siteImages['hero']        ?? null, 'images/hero-traiteur.webp');
        $preparationUrl = imageUrl($siteImages['preparation'] ?? null, 'images/preparation-traiteur-generique.webp');
        $preloadImages   = [$heroUrl];
        $heroSousTitre   = SiteConfigModel::get('hero_sous_titre', siteSlogan());
        $heroParagraphe  = SiteConfigModel::get('hero_paragraphe', '');
        view('pages/home', compact('avisValides', 'preloadImages', 'heroUrl', 'preparationUrl', 'heroSousTitre', 'heroParagraphe'));
    }
}
