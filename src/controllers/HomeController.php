<?php
// src/controllers/HomeController.php
class HomeController {
    public function index(): void {
        $avisValides   = AvisModel::getHomepage();
        $siteImages    = SiteImageModel::getAll();
        $heroUrl       = imageUrl($siteImages['hero']        ?? null, 'images/hero-traiteur-bordeaux.webp');
        $preparationUrl = imageUrl($siteImages['preparation'] ?? null, 'images/preparation-traiteur.webp');
        $preloadImages   = [$heroUrl];
        $heroSousTitre   = SiteConfigModel::get('hero_sous_titre', siteSlogan());
        $heroParagraphe  = SiteConfigModel::get('hero_paragraphe', '');
        view('pages/home', compact('avisValides', 'preloadImages', 'heroUrl', 'preparationUrl', 'heroSousTitre', 'heroParagraphe'));
    }
}
